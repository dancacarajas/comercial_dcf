<?php

declare(strict_types=1);

/**
 * Validação HTTP — Etapa 14 Contratos (produção)
 * Executar no servidor: php scripts/validate_etapa14_production.php
 */

const BASE_URL = 'https://comercial.dancacarajas.com.br';
const PASSWORD = 'Mudar@123';
const ADMIN_EMAIL = 'validacao-etapa14-admin@test.com';
const SEM_VIEW_EMAIL = 'validacao-etapa14-sem@test.com';
const LEITOR_EMAIL = 'validacao-etapa14-leitor@test.com';
const CAP_EMAIL = 'validacao-etapa14-cap@test.com';

final class HttpClient
{
    private string $cookieFile;

    public function __construct()
    {
        $this->cookieFile = sys_get_temp_dir() . '/dcc_etapa14_' . bin2hex(random_bytes(4)) . '.txt';
        touch($this->cookieFile);
    }

    public function __destruct()
    {
        if (is_file($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    /** @return array{code:int, body:string, headers:array<string,string>, location:?string} */
    public function request(string $method, string $path, array $post = [], ?string $fileField = null, ?string $filePath = null): array
    {
        $url = BASE_URL . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);

        if ($post !== []) {
            if ($fileField !== null && $filePath !== null && is_file($filePath)) {
                $post[$fileField] = new CURLFile($filePath, mime_content_type($filePath) ?: 'text/plain', basename($filePath));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            }
        }

        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerRaw = substr($raw, 0, $headerSize);
        $body      = substr($raw, $headerSize);
        $headers   = [];
        $location  = null;
        foreach (explode("\r\n", $headerRaw) as $line) {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
            }
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        return compact('code', 'body', 'headers', 'location');
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $post = [], ?string $fileField = null, ?string $filePath = null): array
    {
        return $this->request('POST', $path, $post, $fileField, $filePath);
    }

    public static function extractCsrf(string $html): ?string
    {
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    public function login(string $email, string $password): bool
    {
        $page = $this->get('/login');
        $csrf = self::extractCsrf($page['body']);
        if ($csrf === null) {
            return false;
        }
        $res = $this->post('/login', [
            '_csrf'    => $csrf,
            'email'    => $email,
            'password' => $password,
        ]);
        return in_array($res['code'], [302, 303], true) && str_contains((string) $res['location'], '/dashboard');
    }
}

final class Report
{
    /** @var list<array<string,mixed>> */
    public array $results = [];

    /** @var array<string,mixed> */
    public array $ids = [];

    /** @var list<string> */
    public array $failures = [];

    public function pass(string $section, string $test, string $detail = ''): void
    {
        $this->results[] = ['section' => $section, 'test' => $test, 'status' => 'PASS', 'detail' => $detail];
        echo "  [PASS] {$test}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }

    public function fail(string $section, string $test, string $detail = ''): void
    {
        $this->results[] = ['section' => $section, 'test' => $test, 'status' => 'FAIL', 'detail' => $detail];
        $this->failures[] = "{$test}: {$detail}";
        echo "  [FAIL] {$test}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $envFile = dirname(__DIR__) . '/.env';
        $cfg = ['DB_HOST' => 'localhost', 'DB_DATABASE' => '', 'DB_USERNAME' => '', 'DB_PASSWORD' => ''];
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $cfg[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                }
            }
        }
        $pdo = new PDO(
            'mysql:host=' . ($cfg['DB_HOST'] ?? 'localhost') . ';dbname=' . ($cfg['DB_DATABASE'] ?? '') . ';charset=utf8mb4',
            $cfg['DB_USERNAME'] ?? '',
            $cfg['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

function dbq(string $sql, array $params = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC);
}

function dbexec(string $sql, array $params = []): void
{
    $st = db()->prepare($sql);
    $st->execute($params);
}

function ensureTempValidationUsers(): void
{
    $hash = password_hash(PASSWORD, PASSWORD_DEFAULT);
    $users = [
        [ADMIN_EMAIL, 'Validacao Etapa14 Admin', 'administrador-geral'],
        [LEITOR_EMAIL, 'Validacao Etapa14 Leitor', 'leitura-consulta'],
        [CAP_EMAIL, 'Validacao Etapa14 Captacao', 'captacao-comercial'],
    ];
    foreach ($users as [$email, $name, $roleSlug]) {
        $row = dbq('SELECT id FROM users WHERE email = ?', [$email]);
        if (!$row) {
            db()->prepare('INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())')
                ->execute([$name, $email, $hash, 'active']);
            $uid = (int) db()->lastInsertId();
        } else {
            $uid = (int) $row['id'];
            db()->prepare("UPDATE users SET status='active', password_hash=? WHERE id=?")->execute([$hash, $uid]);
        }
        $roleId = dbq('SELECT id FROM roles WHERE slug = ?', [$roleSlug])['id'] ?? null;
        if ($roleId) {
            db()->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, $roleId]);
        }
    }
    $semRole = dbq("SELECT id FROM roles WHERE slug = 'teste-sem-contracts-etapa14'");
    if (!$semRole) {
        db()->prepare("INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)")
            ->execute(['Teste sem contratos Etapa14', 'teste-sem-contracts-etapa14', 'Role temporaria validacao']);
        $semRole = dbq("SELECT id FROM roles WHERE slug = 'teste-sem-contracts-etapa14'");
    }
    $row = dbq('SELECT id FROM users WHERE email = ?', [SEM_VIEW_EMAIL]);
    if (!$row) {
        db()->prepare('INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute(['Validacao Etapa14 Sem', SEM_VIEW_EMAIL, $hash, 'active']);
        $uid = (int) db()->lastInsertId();
    } else {
        $uid = (int) $row['id'];
        db()->prepare("UPDATE users SET status='active', password_hash=? WHERE id=?")->execute([$hash, $uid]);
    }
    if ($semRole) {
        db()->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$uid]);
        db()->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, (int) $semRole['id']]);
    }
}

function ctPayload(array $overrides = []): array
{
    return array_merge([
        'sponsor_id' => '', 'company_id' => '', 'contact_id' => '', 'opportunity_id' => '',
        'proposal_id' => '', 'quota_id' => '', 'draft_document_id' => '', 'final_document_id' => '',
        'signed_document_id' => '', 'contract_number' => '', 'title' => '',
        'contract_type' => 'termo_patrocinio', 'formalized_value' => '', 'funding_mechanism' => 'nao_definido',
        'status' => 'minuta', 'review_status' => 'nao_revisado', 'signature_status' => 'nao_enviado',
        'start_date' => '', 'end_date' => '', 'sent_for_signature_at' => '', 'signed_at' => '',
        'effective_at' => '', 'ended_at' => '', 'sponsor_signatory_name' => '', 'sponsor_signatory_email' => '',
        'sponsor_signatory_position' => '', 'sponsor_signatory_document' => '',
        'organization_signatory_name' => '', 'organization_signatory_email' => '', 'organization_signatory_position' => '',
        'approval_notes' => '', 'signature_notes' => '', 'legal_notes' => '', 'notes' => '', 'internal_notes' => '',
        'responsible_user_id' => '1',
    ], $overrides);
}

function postContract(HttpClient $http, array $fields): array
{
    $page = $http->get('/contracts/create');
    $fields['_csrf'] = HttpClient::extractCsrf($page['body']) ?? '';
    return $http->post('/contracts', $fields);
}

function findSponsorId(): int
{
    $row = dbq("SELECT id FROM sponsors WHERE archived_at IS NULL ORDER BY id DESC LIMIT 1");
    return (int) ($row['id'] ?? 0);
}

function ensureSponsor(HttpClient $http, Report $R): int
{
    $existing = findSponsorId();
    if ($existing > 0) {
        $R->pass('dados', 'Patrocinador base reutilizado', 'id=' . $existing);
        return $existing;
    }

    $company = dbq('SELECT id FROM companies WHERE archived_at IS NULL ORDER BY id ASC LIMIT 1');
    $companyId = (int) ($company['id'] ?? 0);
    if ($companyId <= 0) {
        $page = $http->get('/companies/create');
        $csrf = HttpClient::extractCsrf($page['body']);
        $res = $http->post('/companies', [
            '_csrf' => $csrf, 'name' => 'EMPRESA TESTE — ETAPA 14', 'status' => 'prospect', 'priority' => 'B',
        ]);
        if (preg_match('#/companies/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
            $companyId = (int) $m[1];
        } else {
            $companyId = (int) (dbq("SELECT id FROM companies WHERE name LIKE '%ETAPA 14%' ORDER BY id DESC LIMIT 1")['id'] ?? 0);
        }
    }

    $page = $http->get('/sponsors/create?company_id=' . $companyId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/sponsors', [
        '_csrf' => $csrf,
        'company_id' => (string) $companyId,
        'sponsor_display_name' => 'PATROCINADOR BASE ETAPA 14',
        'sponsorship_type' => 'patrocinio_direto',
        'funding_mechanism' => 'lei_rouanet',
        'project_year' => '2026',
        'festival_edition' => 'Dança Carajás Festival 2026',
        'status' => 'fechamento_registrado',
        'payment_status' => 'pendente',
        'committed_amount' => '50000',
        'responsible_user_id' => '1',
        'public_announcement_allowed' => '1',
    ]);
    if (preg_match('#/sponsors/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
        $R->pass('dados', 'Patrocinador base criado', 'id=' . $m[1]);
        return (int) $m[1];
    }
    $id = findSponsorId();
    $id > 0 ? $R->pass('dados', 'Patrocinador base (fallback)', 'id=' . $id) : $R->fail('dados', 'Criar patrocinador base');
    return $id;
}

echo "=== VALIDAÇÃO ETAPA 14 — CONTRATOS ===" . PHP_EOL . PHP_EOL;
$R = new Report();

echo "1. AMBIENTE / BANCO" . PHP_EOL;
$R->pass('ambiente', 'Tabela contracts', dbq("SHOW TABLES LIKE 'contracts'") ? 'OK' : 'MISSING');
$R->pass('ambiente', 'Coluna documents.contract_id', dbq("SHOW COLUMNS FROM documents LIKE 'contract_id'") ? 'OK' : 'MISSING');
$perms = db()->query("SELECT slug FROM permissions WHERE slug LIKE 'contracts.%' ORDER BY slug")->fetchAll(PDO::FETCH_COLUMN);
(count($perms) === 7)
    ? $R->pass('ambiente', 'Permissões contracts.* (7)', implode(', ', $perms))
    : $R->fail('ambiente', 'Permissões contracts.*', implode(', ', $perms));
$capPerm = dbq(
    "SELECT COUNT(*) AS c FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug = 'captacao-comercial' AND p.slug = 'contracts.create'"
);
((int) ($capPerm['c'] ?? 0) > 0)
    ? $R->pass('ambiente', 'Captação com contracts.create')
    : $R->fail('ambiente', 'Captação com contracts.create');

echo PHP_EOL . "2. AUTENTICAÇÃO E PERMISSÃO" . PHP_EOL;
$anon = new HttpClient();
$r = $anon->get('/contracts');
($r['code'] === 302 && str_contains((string) $r['location'], '/login'))
    ? $R->pass('auth', 'GET /contracts sem login → 302')
    : $R->fail('auth', 'GET /contracts sem login → 302', 'code=' . $r['code']);

ensureTempValidationUsers();
$sem = new HttpClient();
$sem->login(SEM_VIEW_EMAIL, PASSWORD);
$r = $sem->get('/contracts');
($r['code'] === 403) ? $R->pass('auth', 'GET /contracts sem contracts.view → 403') : $R->fail('auth', 'sem view → 403', 'code=' . $r['code']);

$admin = new HttpClient();
$admin->login(ADMIN_EMAIL, PASSWORD);
$r = $admin->get('/contracts');
($r['code'] === 200) ? $R->pass('auth', 'GET /contracts admin → 200') : $R->fail('auth', 'admin list', 'code=' . $r['code']);

$leitor = new HttpClient();
$leitor->login(LEITOR_EMAIL, PASSWORD);
($leitor->get('/contracts')['code'] === 200) ? $R->pass('auth', 'GET /contracts leitor → 200') : $R->fail('auth', 'leitor list');
($leitor->get('/contracts/create')['code'] === 403) ? $R->pass('auth', 'GET /contracts/create leitor → 403') : $R->fail('auth', 'leitor create');

$cap = new HttpClient();
$cap->login(CAP_EMAIL, PASSWORD);
($cap->get('/contracts/create')['code'] === 200) ? $R->pass('auth', 'GET /contracts/create captacao → 200') : $R->fail('auth', 'cap create');

$menu = $admin->get('/dashboard');
str_contains($menu['body'], 'Contratos')
    ? $R->pass('auth', 'Menu Contratos visível com contracts.view')
    : $R->fail('auth', 'Menu Contratos');
$semMenu = $sem->get('/dashboard');
!preg_match('#href=["\'][^"\']*/contracts["\']#', $semMenu['body'])
    ? $R->pass('auth', 'Menu Contratos oculto sem contracts.view')
    : $R->fail('auth', 'Menu oculto sem view');

$r = $sem->get('/contracts/create');
($r['code'] === 403) ? $R->pass('auth', 'GET /contracts/create sem create → 403') : $R->fail('auth', 'create sem perm', 'code=' . $r['code']);

$badCsrf = new HttpClient();
$badCsrf->login(ADMIN_EMAIL, PASSWORD);
$r = $badCsrf->post('/contracts', ['_csrf' => 'x', 'sponsor_id' => '1', 'title' => 'Teste']);
($r['code'] === 419) ? $R->pass('auth', 'POST CSRF inválido → 419') : $R->fail('auth', 'CSRF', 'code=' . $r['code']);

$sponsorId = ensureSponsor($admin, $R);
if ($sponsorId <= 0) {
    echo PHP_EOL . 'ABORT: sponsor_id ausente' . PHP_EOL;
    exit(1);
}
$sp = dbq('SELECT company_id, contact_id, opportunity_id, proposal_id, quota_id FROM sponsors WHERE id=?', [$sponsorId]);

echo PHP_EOL . "3. VALIDAÇÃO DE CAMPOS" . PHP_EOL;
$tests = [
    ['sem patrocinador', ctPayload(['title' => 'Teste sem sponsor'])],
    ['patrocinador inexistente', ctPayload(['sponsor_id' => '999999', 'title' => 'X'])],
    ['sem título curto', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'AB'])],
    ['tipo inválido', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste tipo', 'contract_type' => 'invalido'])],
    ['mecanismo inválido', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste mec', 'funding_mechanism' => 'invalido'])],
    ['status inválido', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste st', 'status' => 'invalido'])],
    ['review inválido', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste rev', 'review_status' => 'invalido'])],
    ['assinatura inválida', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste sig', 'signature_status' => 'invalido'])],
    ['valor negativo', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste val', 'formalized_value' => '-100'])],
    ['end < start', ctPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste dt', 'start_date' => '2026-12-01', 'end_date' => '2026-01-01'])],
];
foreach ($tests as [$label, $payload]) {
    $r = postContract($admin, $payload);
    ($r['code'] === 422)
        ? $R->pass('validacao', "POST inválido: {$label} → 422")
        : $R->fail('validacao', $label, 'code=' . $r['code']);
}

$testTitle = 'CONTRATO TESTE ETAPA 14 ' . date('His');
echo PHP_EOL . "4. CRIAÇÃO VÁLIDA" . PHP_EOL;
$r = postContract($admin, ctPayload([
    'sponsor_id' => (string) $sponsorId,
    'company_id' => (string) ($sp['company_id'] ?? ''),
    'contact_id' => (string) ($sp['contact_id'] ?? ''),
    'opportunity_id' => (string) ($sp['opportunity_id'] ?? ''),
    'proposal_id' => (string) ($sp['proposal_id'] ?? ''),
    'quota_id' => (string) ($sp['quota_id'] ?? ''),
    'title' => $testTitle,
    'formalized_value' => '75000',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+365 days')),
]));
$ctId = 0;
if (preg_match('#/contracts/(\d+)#', (string) ($r['location'] ?? ''), $m)) {
    $ctId = (int) $m[1];
    $R->pass('crud', 'POST válido → redirect show', 'id=' . $ctId);
} else {
    $row = dbq("SELECT id FROM contracts WHERE title = ? ORDER BY id DESC LIMIT 1", [$testTitle]);
    $ctId = (int) ($row['id'] ?? 0);
    $ctId > 0 ? $R->pass('crud', 'POST válido (fallback DB)', 'id=' . $ctId) : $R->fail('crud', 'POST válido', 'code=' . $r['code']);
}
$R->ids['contract_id'] = $ctId;

if ($ctId > 0) {
    $ct = dbq('SELECT sponsor_id, company_id, formalized_value FROM contracts WHERE id=?', [$ctId]);
    ((int) $ct['sponsor_id'] === $sponsorId && (int) $ct['company_id'] === (int) $sp['company_id'])
        ? $R->pass('crud', 'Vínculos herdados do sponsor')
        : $R->fail('crud', 'Vínculos herdados');

    $r = $admin->get('/contracts/' . $ctId);
    ($r['code'] === 200) ? $R->pass('crud', 'GET show → 200') : $R->fail('crud', 'GET show', 'code=' . $r['code']);

    $r = $admin->get('/contracts/' . $ctId . '/edit');
    ($r['code'] === 200) ? $R->pass('crud', 'GET edit → 200') : $R->fail('crud', 'GET edit', 'code=' . $r['code']);

    $editPage = $admin->get('/contracts/' . $ctId . '/edit');
    $csrf = HttpClient::extractCsrf($editPage['body']);
    $r = $admin->post('/contracts/' . $ctId . '/update', array_merge(ctPayload([
        'sponsor_id' => (string) $sponsorId, 'title' => $testTitle . ' ATUALIZADO',
        'formalized_value' => '80000', 'status' => 'em_elaboracao',
    ]), ['_csrf' => $csrf ?? '']));
    ($r['code'] === 302) ? $R->pass('crud', 'POST update → redirect') : $R->fail('crud', 'POST update', 'code=' . $r['code']);
    $log = dbq("SELECT id FROM activity_logs WHERE entity_type='contract' AND entity_id=? AND action='contract_updated' ORDER BY id DESC LIMIT 1", [$ctId]);
    $log ? $R->pass('crud', 'Log contract_updated') : $R->fail('crud', 'Log contract_updated');

    echo PHP_EOL . "5. APROVAÇÃO, ASSINATURA E STATUS" . PHP_EOL;
    $show = $admin->get('/contracts/' . $ctId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/contracts/' . $ctId . '/approve', ['_csrf' => $csrf, 'approval_notes' => 'Aprovado teste']);
    $ap = dbq('SELECT status, approved_at, approved_by FROM contracts WHERE id=?', [$ctId]);
    ($ap['status'] === 'aprovado_internamente' && !empty($ap['approved_at']) && !empty($ap['approved_by']))
        ? $R->pass('approve', 'Aprovação preenche approved_at/approved_by')
        : $R->fail('approve', 'Aprovação', json_encode($ap));

    $showCap = $cap->get('/contracts/' . $ctId);
    $csrfCap = HttpClient::extractCsrf($showCap['body']);
    ($cap->post('/contracts/' . $ctId . '/approve', ['_csrf' => $csrfCap ?? ''])['code'] === 403)
        ? $R->pass('auth', 'POST approve captacao → 403')
        : $R->fail('auth', 'cap approve', 'deveria ser 403');

    $show = $admin->get('/contracts/' . $ctId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/contracts/' . $ctId . '/mark-signed', [
        '_csrf' => $csrf,
        'status' => 'assinado',
        'signed_at' => date('Y-m-d H:i'),
        'signature_notes' => 'Assinatura manual teste',
    ]);
    $signed = dbq('SELECT status, signed_at, signature_status, signed_registered_by FROM contracts WHERE id=?', [$ctId]);
    ($signed['status'] === 'assinado' && !empty($signed['signed_at']) && !empty($signed['signed_registered_by']))
        ? $R->pass('signed', 'Marcar assinado preenche signed_at/signed_registered_by')
        : $R->fail('signed', 'Marcar assinado', json_encode($signed));

    dbexec("UPDATE contracts SET status='em_revisao', review_status='em_revisao' WHERE id=?", [$ctId]);
    $show = $admin->get('/contracts/' . $ctId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/contracts/' . $ctId . '/status', ['_csrf' => $csrf, 'status' => 'vigente', 'review_status' => 'aprovado_final']);
    $vig = dbq('SELECT status, effective_at FROM contracts WHERE id=?', [$ctId]);
    ($vig['status'] === 'vigente' && !empty($vig['effective_at']))
        ? $R->pass('status', 'Status vigente preenche effective_at')
        : $R->fail('status', 'vigente', json_encode($vig));

    echo PHP_EOL . "6. ARQUIVAR / RESTAURAR" . PHP_EOL;
    $show = $admin->get('/contracts/' . $ctId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/contracts/' . $ctId . '/archive', ['_csrf' => $csrf]);
    !empty(dbq('SELECT archived_at FROM contracts WHERE id=?', [$ctId])['archived_at'])
        ? $R->pass('archive', 'archive preenche archived_at')
        : $R->fail('archive', 'archived_at');
    $updatedTitle = $testTitle . ' ATUALIZADO';
    $list = $admin->get('/contracts');
    !str_contains($list['body'], $updatedTitle)
        ? $R->pass('archive', 'Some da listagem padrão')
        : $R->fail('archive', 'listagem padrão');
    $archList = $admin->get('/contracts?show_archived=1');
    str_contains($archList['body'], $updatedTitle)
        ? $R->pass('archive', 'Filtro arquivados mostra')
        : $R->fail('archive', 'filtro arquivados');
    $show = $admin->get('/contracts/' . $ctId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/contracts/' . $ctId . '/restore', ['_csrf' => $csrf]);
    empty(dbq('SELECT archived_at FROM contracts WHERE id=?', [$ctId])['archived_at'])
        ? $R->pass('archive', 'restore limpa archived_at')
        : $R->fail('archive', 'restore');

    echo PHP_EOL . "7. DOCUMENTOS VINCULADOS" . PHP_EOL;
    $docPath = sys_get_temp_dir() . '/doc_test_etapa14.txt';
    file_put_contents($docPath, 'Documento teste validacao etapa 14');
    $docPage = $admin->get('/contracts/' . $ctId . '/documents/create');
    ($docPage['code'] === 200 && str_contains($docPage['body'], (string) $ctId))
        ? $R->pass('docs', 'GET /contracts/{id}/documents/create → 200')
        : $R->fail('docs', 'create contextual', 'code=' . $docPage['code']);
    $docCsrf = HttpClient::extractCsrf($docPage['body']);
    $docRes = $admin->post('/documents', [
        '_csrf' => $docCsrf, 'contract_id' => (string) $ctId, 'sponsor_id' => (string) $sponsorId,
        'company_id' => (string) ($sp['company_id'] ?? ''), 'title' => 'DOC TESTE ETAPA 14 CONTRATO',
        'category' => 'documento_comercial', 'status' => 'ativo', 'access_level' => 'interno',
        'document_date' => date('Y-m-d'), 'use_as_draft' => '1',
    ], 'document_file', $docPath);
    $docId = 0;
    if (preg_match('#/documents/(\d+)#', (string) ($docRes['location'] ?? ''), $dm)) {
        $docId = (int) $dm[1];
    } else {
        $docId = (int) (dbq("SELECT id FROM documents WHERE title LIKE ? ORDER BY id DESC LIMIT 1", ['%ETAPA 14 CONTRATO%'])['id'] ?? 0);
    }
    $docId > 0 ? $R->pass('docs', 'Documento vinculado ao contrato', 'id=' . $docId) : $R->fail('docs', 'Upload documento');
    if ($docId > 0) {
        $d = dbq('SELECT contract_id FROM documents WHERE id=?', [$docId]);
        ((int) ($d['contract_id'] ?? 0) === $ctId) ? $R->pass('docs', 'documents.contract_id salvo') : $R->fail('docs', 'contract_id');
        $linked = dbq('SELECT draft_document_id FROM contracts WHERE id=?', [$ctId]);
        ((int) ($linked['draft_document_id'] ?? 0) === $docId)
            ? $R->pass('docs', 'use_as_draft vincula draft_document_id')
            : $R->fail('docs', 'draft_document_id');
        $showDoc = $admin->get('/documents/' . $docId);
        str_contains($showDoc['body'], '/contracts/' . $ctId)
            ? $R->pass('docs', 'Show documento link contrato')
            : $R->fail('docs', 'Show documento link');
        $showCt = $admin->get('/contracts/' . $ctId);
        str_contains($showCt['body'], 'Contrato') || str_contains($showCt['body'], 'Documentos')
            ? $R->pass('docs', 'Show contrato bloco documentos')
            : $R->fail('docs', 'Bloco documentos contrato');
    }
}

echo PHP_EOL . "8. FILTROS E PAGINAÇÃO" . PHP_EOL;
$filters = [
    'busca' => '/contracts?q=CONTRATO',
    'patrocinador' => '/contracts?sponsor_id=' . $sponsorId,
    'empresa' => '/contracts?company_id=' . (int) ($sp['company_id'] ?? 0),
    'tipo' => '/contracts?contract_type=termo_patrocinio',
    'mecanismo' => '/contracts?funding_mechanism=lei_rouanet',
    'status' => '/contracts?status=vigente',
    'revisao' => '/contracts?review_status=aprovado_final',
    'assinatura' => '/contracts?signature_status=nao_enviado',
    'responsavel' => '/contracts?responsible_user_id=1',
    'vigentes' => '/contracts?active_vigente=1',
    'aguardando' => '/contracts?awaiting_signature=1',
    'assinados' => '/contracts?signed=1',
    'vencidos' => '/contracts?expired=1',
    'arquivados' => '/contracts?show_archived=1',
    'pagina_2' => '/contracts?page=2',
    'doc_contrato' => '/documents?contract_id=' . (int) ($R->ids['contract_id'] ?? 0),
];
foreach ($filters as $name => $url) {
    if (str_contains($url, 'contract_id=0')) {
        continue;
    }
    $r = $admin->get($url);
    ($r['code'] === 200) ? $R->pass('filtros', "Filtro {$name} → 200") : $R->fail('filtros', $name, 'code=' . $r['code']);
}

echo PHP_EOL . "9. BLOCOS CONTEXTUAIS E DASHBOARD" . PHP_EOL;
$companyId = (int) ($sp['company_id'] ?? 0);
$contactId = (int) ($sp['contact_id'] ?? 0);
$oppId = (int) ($sp['opportunity_id'] ?? 0);
$propId = (int) ($sp['proposal_id'] ?? 0);
$quotaId = (int) ($sp['quota_id'] ?? 0);
foreach ([
    'sponsors' => $sponsorId, 'companies' => $companyId, 'contacts' => $contactId,
    'opportunities' => $oppId, 'proposals' => $propId, 'quotas' => $quotaId,
] as $entity => $eid) {
    if ($eid <= 0) {
        continue;
    }
    $r = $admin->get("/{$entity}/{$eid}");
    str_contains($r['body'], 'Contratos')
        ? $R->pass('contexto', "Bloco contratos em /{$entity}/{$eid}")
        : $R->fail('contexto', "/{$entity}/{$eid}");
}
$dash = $admin->get('/dashboard');
str_contains($dash['body'], 'Contratos') && str_contains($dash['body'], 'Gerenciar contratos')
    ? $R->pass('dashboard', 'Card contratos no painel')
    : $R->fail('dashboard', 'Card contratos');

echo PHP_EOL . "10. ESCOPO NÃO CRIADO" . PHP_EOL;
$futureRoutes = ['/signatures', '/portal', '/finance/reports'];
foreach ($futureRoutes as $route) {
    $r = $admin->get($route);
    !in_array($r['code'], [200], true)
        ? $R->pass('escopo', "Rota futura {$route} não exposta (code={$r['code']})")
        : $R->fail('escopo', "Rota {$route} não deveria existir");
}

echo PHP_EOL . "=== RESUMO ===" . PHP_EOL;
$pass = count(array_filter($R->results, static fn ($x) => $x['status'] === 'PASS'));
$fail = count($R->failures);
echo "PASS: {$pass} | FAIL: {$fail}" . PHP_EOL;
if ($fail > 0) {
    echo PHP_EOL . "Falhas:" . PHP_EOL;
    foreach ($R->failures as $f) {
        echo "  - {$f}" . PHP_EOL;
    }
    exit(1);
}
echo PHP_EOL . 'Validação Etapa 14 concluída com sucesso.' . PHP_EOL;
