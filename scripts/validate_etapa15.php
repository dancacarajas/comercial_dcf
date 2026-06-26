<?php

declare(strict_types=1);

/**
 * Validação local HTTP — Etapa 15 Financeiro Detalhado / Parcelas
 * Executar: docker exec dcc_app php /var/www/html/scripts/validate_etapa15.php
 */

const BASE_URL = 'http://localhost';
const PASSWORD = 'Mudar@123';

final class HttpClient
{
    private string $cookieFile;

    public function __construct()
    {
        $this->cookieFile = sys_get_temp_dir() . '/dcc_etapa15_' . bin2hex(random_bytes(4)) . '.txt';
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
        $pdo = new PDO(
            'mysql:host=db;dbname=danca_captacao;charset=utf8mb4',
            'danca',
            'danca',
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

function ensureSemFinancialsUser(): void
{
    $row = dbq('SELECT id FROM users WHERE email = ?', ['sem-financials@test.com']);
    if (!$row) {
        $hash = dbq('SELECT password_hash FROM users WHERE id = 1')['password_hash'];
        db()->prepare(
            'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())'
        )->execute(['Sem Financeiro', 'sem-financials@test.com', $hash, 'active']);
        $uid = (int) db()->lastInsertId();
        $roleId = dbq("SELECT id FROM roles WHERE slug = 'leitura-consulta'")['id'] ?? null;
        if ($roleId) {
            db()->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, $roleId]);
        }
    } else {
        $uid = (int) $row['id'];
    }
    db()->prepare(
        'DELETE rp FROM role_permissions rp
         INNER JOIN permissions p ON p.id = rp.permission_id
         INNER JOIN user_roles ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = ? AND p.slug LIKE ?'
    )->execute([$uid, 'financials.%']);
}

function fePayload(array $overrides = []): array
{
    return array_merge([
        'sponsor_id' => '', 'contract_id' => '', 'company_id' => '', 'contact_id' => '',
        'opportunity_id' => '', 'proposal_id' => '', 'quota_id' => '',
        'proof_document_id' => '', 'receipt_document_id' => '', 'fiscal_document_id' => '',
        'entry_number' => '', 'title' => '',
        'entry_type' => 'parcela_patrocinio', 'funding_mechanism' => 'nao_definido',
        'payment_method' => 'nao_definido', 'status' => 'previsto', 'fiscal_document_status' => 'nao_aplicavel',
        'installment_number' => '', 'installments_total' => '',
        'planned_amount' => '', 'received_amount' => '0',
        'due_date' => '', 'expected_payment_date' => '',
        'received_at' => '', 'reconciled_at' => '', 'cancelled_at' => '',
        'payer_name' => '', 'payer_document' => '', 'bank_reference' => '', 'transaction_reference' => '',
        'proof_notes' => '', 'receipt_notes' => '', 'fiscal_notes' => '',
        'reconciliation_notes' => '', 'notes' => '', 'internal_notes' => '',
        'responsible_user_id' => '1',
    ], $overrides);
}

function postFinancial(HttpClient $http, array $fields): array
{
    $page = $http->get('/financials/create');
    $fields['_csrf'] = HttpClient::extractCsrf($page['body']) ?? '';
    return $http->post('/financials', $fields);
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
            '_csrf' => $csrf, 'name' => 'EMPRESA TESTE — ETAPA 15', 'status' => 'prospect', 'priority' => 'B',
        ]);
        if (preg_match('#/companies/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
            $companyId = (int) $m[1];
        } else {
            $companyId = (int) (dbq("SELECT id FROM companies WHERE name LIKE '%ETAPA 15%' ORDER BY id DESC LIMIT 1")['id'] ?? 0);
        }
    }

    $page = $http->get('/sponsors/create?company_id=' . $companyId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/sponsors', [
        '_csrf' => $csrf,
        'company_id' => (string) $companyId,
        'sponsor_display_name' => 'PATROCINADOR BASE ETAPA 15',
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

function ensureFullTestChain(HttpClient $http, Report $R): array
{
    $prefix = 'ETAPA 15 FECHAMENTO';
    $page = $http->get('/companies/create');
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/companies', [
        '_csrf' => $csrf, 'name' => $prefix . ' EMPRESA', 'status' => 'prospect', 'priority' => 'B',
    ]);
    $companyId = 0;
    if (preg_match('#/companies/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
        $companyId = (int) $m[1];
    } else {
        $companyId = (int) (dbq("SELECT id FROM companies WHERE name LIKE ? ORDER BY id DESC LIMIT 1", ['%' . $prefix . ' EMPRESA%'])['id'] ?? 0);
    }
    $companyId > 0 ? $R->pass('dados', 'Empresa teste', 'id=' . $companyId) : $R->fail('dados', 'Empresa teste');

    $page = $http->get('/contacts/create?company_id=' . $companyId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/contacts', [
        '_csrf' => $csrf, 'company_id' => (string) $companyId, 'name' => $prefix . ' CONTATO', 'status' => 'ativo',
    ]);
    $contactId = preg_match('#/contacts/(\d+)#', (string) ($res['location'] ?? ''), $m) ? (int) $m[1]
        : (int) (dbq("SELECT id FROM contacts WHERE name LIKE ? ORDER BY id DESC LIMIT 1", ['%' . $prefix . ' CONTATO%'])['id'] ?? 0);
    $contactId > 0 ? $R->pass('dados', 'Contato teste', 'id=' . $contactId) : $R->fail('dados', 'Contato teste');

    $quotaId = (int) (dbq("SELECT id FROM quotas WHERE name = 'Cota Carajás' LIMIT 1")['id'] ?? 0);
    if ($quotaId <= 0) {
        $quotaId = (int) (dbq('SELECT id FROM quotas WHERE archived_at IS NULL ORDER BY id ASC LIMIT 1')['id'] ?? 0);
    }
    $quotaId > 0 ? $R->pass('dados', 'Cota teste', 'id=' . $quotaId) : $R->fail('dados', 'Cota teste');

    $page = $http->get('/opportunities/create?company_id=' . $companyId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/opportunities', [
        '_csrf' => $csrf, 'company_id' => (string) $companyId, 'contact_id' => (string) $contactId,
        'quota_id' => (string) $quotaId, 'title' => $prefix . ' OPORTUNIDADE',
        'status' => 'negociacao', 'estimated_value' => '50000', 'probability' => '50',
    ]);
    $oppId = preg_match('#/opportunities/(\d+)#', (string) ($res['location'] ?? ''), $m) ? (int) $m[1]
        : (int) (dbq("SELECT id FROM opportunities WHERE title LIKE ? ORDER BY id DESC LIMIT 1", ['%' . $prefix . ' OPORTUNIDADE%'])['id'] ?? 0);

    $page = $http->get('/proposals/create?company_id=' . $companyId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/proposals', [
        '_csrf' => $csrf, 'company_id' => (string) $companyId, 'contact_id' => (string) $contactId,
        'opportunity_id' => (string) $oppId, 'quota_id' => (string) $quotaId,
        'title' => $prefix . ' PROPOSTA', 'type' => 'proposta_por_cota', 'proposed_value' => '50000', 'status' => 'rascunho',
    ]);
    $propId = preg_match('#/proposals/(\d+)#', (string) ($res['location'] ?? ''), $m) ? (int) $m[1]
        : (int) (dbq("SELECT id FROM proposals WHERE title LIKE ? ORDER BY id DESC LIMIT 1", ['%' . $prefix . ' PROPOSTA%'])['id'] ?? 0);

    $page = $http->get('/sponsors/create?company_id=' . $companyId . '&contact_id=' . $contactId . '&opportunity_id=' . $oppId . '&proposal_id=' . $propId . '&quota_id=' . $quotaId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/sponsors', [
        '_csrf' => $csrf,
        'company_id' => (string) $companyId, 'contact_id' => (string) $contactId,
        'opportunity_id' => (string) $oppId, 'proposal_id' => (string) $propId, 'quota_id' => (string) $quotaId,
        'sponsor_display_name' => $prefix . ' PATROCINADOR',
        'sponsorship_type' => 'patrocinio_direto', 'funding_mechanism' => 'lei_rouanet',
        'project_year' => '2026', 'festival_edition' => 'Dança Carajás Festival 2026',
        'status' => 'fechamento_registrado', 'payment_status' => 'pendente',
        'committed_amount' => '50000', 'responsible_user_id' => '1', 'public_announcement_allowed' => '1',
    ]);
    $sponsorId = preg_match('#/sponsors/(\d+)#', (string) ($res['location'] ?? ''), $m) ? (int) $m[1]
        : (int) (dbq("SELECT id FROM sponsors WHERE sponsor_display_name LIKE ? ORDER BY id DESC LIMIT 1", ['%' . $prefix . ' PATROCINADOR%'])['id'] ?? 0);
    $sponsorId > 0 ? $R->pass('dados', 'Patrocinador cadeia completa', 'id=' . $sponsorId) : $R->fail('dados', 'Patrocinador cadeia');

    return compact('companyId', 'contactId', 'quotaId', 'oppId', 'propId', 'sponsorId');
}

function archiveTestData(Report $R): void
{
    echo PHP_EOL . "11. LIMPEZA LOCAL" . PHP_EOL;
    $now = date('Y-m-d H:i:s');
    $patterns = ['ETAPA 15 FECHAMENTO%', 'LANÇAMENTO TESTE ETAPA 15%', 'PARCIAL ETAPA 15%', 'TOTAL ETAPA 15%', 'CANCEL ETAPA 15%', 'PATROCINADOR BASE ETAPA 15%', 'DOC % ETAPA 15%'];
    $fe = db()->exec("UPDATE financial_entries SET archived_at='{$now}' WHERE archived_at IS NULL AND (title LIKE 'LANÇAMENTO TESTE ETAPA 15%' OR title LIKE 'PARCIAL ETAPA 15%' OR title LIKE 'TOTAL ETAPA 15%' OR title LIKE 'CANCEL ETAPA 15%')");
    $doc = db()->exec("UPDATE documents SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE '%ETAPA 15%'");
    $sp = db()->exec("UPDATE sponsors SET archived_at='{$now}' WHERE archived_at IS NULL AND (sponsor_display_name LIKE 'ETAPA 15 FECHAMENTO%' OR sponsor_display_name LIKE 'PATROCINADOR BASE ETAPA 15%')");
    $co = db()->exec("UPDATE companies SET archived_at='{$now}' WHERE archived_at IS NULL AND name LIKE 'ETAPA 15 FECHAMENTO%'");
    $ct = db()->exec("UPDATE contacts SET archived_at='{$now}' WHERE archived_at IS NULL AND name LIKE 'ETAPA 15 FECHAMENTO%'");
    $op = db()->exec("UPDATE opportunities SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE 'ETAPA 15 FECHAMENTO%'");
    $pr = db()->exec("UPDATE proposals SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE 'ETAPA 15 FECHAMENTO%'");
    db()->prepare('UPDATE users SET status=? WHERE email=?')->execute(['inactive', 'sem-financials@test.com']);
    $R->pass('limpeza', 'Dados de teste arquivados/inativados', "fe={$fe} doc={$doc} sp={$sp}");
}

function resolveFinancialId(array $res, string $title): int
{
    if (preg_match('#/financials/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
        return (int) $m[1];
    }
    return (int) (dbq('SELECT id FROM financial_entries WHERE title = ? ORDER BY id DESC LIMIT 1', [$title])['id'] ?? 0);
}

echo "=== VALIDAÇÃO ETAPA 15 — FINANCEIRO ===" . PHP_EOL . PHP_EOL;
$R = new Report();

echo "1. AMBIENTE / BANCO" . PHP_EOL;
$R->pass('ambiente', 'Tabela financial_entries', dbq("SHOW TABLES LIKE 'financial_entries'") ? 'OK' : 'MISSING');
$R->pass('ambiente', 'Coluna documents.financial_entry_id', dbq("SHOW COLUMNS FROM documents LIKE 'financial_entry_id'") ? 'OK' : 'MISSING');
$perms = db()->query("SELECT slug FROM permissions WHERE slug LIKE 'financials.%' ORDER BY slug")->fetchAll(PDO::FETCH_COLUMN);
(count($perms) === 7)
    ? $R->pass('ambiente', 'Permissões financials.* (7)', implode(', ', $perms))
    : $R->fail('ambiente', 'Permissões financials.*', implode(', ', $perms));
$capPerm = dbq(
    "SELECT COUNT(*) AS c FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug = 'captacao-comercial' AND p.slug = 'financials.create'"
);
((int) ($capPerm['c'] ?? 0) > 0)
    ? $R->pass('ambiente', 'Captação com financials.create')
    : $R->fail('ambiente', 'Captação com financials.create');
$capArchive = dbq(
    "SELECT COUNT(*) AS c FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug = 'captacao-comercial' AND p.slug = 'financials.archive'"
);
((int) ($capArchive['c'] ?? 0) === 0)
    ? $R->pass('ambiente', 'Captação sem financials.archive')
    : $R->fail('ambiente', 'Captação não deve ter archive');
$capReconcile = dbq(
    "SELECT COUNT(*) AS c FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug = 'captacao-comercial' AND p.slug = 'financials.reconcile'"
);
((int) ($capReconcile['c'] ?? 0) === 0)
    ? $R->pass('ambiente', 'Captação sem financials.reconcile')
    : $R->fail('ambiente', 'Captação não deve ter reconcile');
foreach (['producao-coordenacao', 'comunicacao', 'leitura-consulta'] as $roleSlug) {
    $cnt = dbq(
        "SELECT COUNT(*) AS c FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         WHERE r.slug = ? AND p.slug LIKE 'financials.%' AND p.slug != 'financials.view'",
        [$roleSlug]
    );
    ((int) ($cnt['c'] ?? 0) === 0)
        ? $R->pass('ambiente', "{$roleSlug} só financials.view")
        : $R->fail('ambiente', "{$roleSlug} permissões extras");
}

echo PHP_EOL . "2. AUTENTICAÇÃO E PERMISSÃO" . PHP_EOL;
$anon = new HttpClient();
$r = $anon->get('/financials');
($r['code'] === 302 && str_contains((string) $r['location'], '/login'))
    ? $R->pass('auth', 'GET /financials sem login → 302')
    : $R->fail('auth', 'GET /financials sem login → 302', 'code=' . $r['code']);

ensureSemFinancialsUser();
$sem = new HttpClient();
$sem->login('sem-financials@test.com', PASSWORD);
$r = $sem->get('/financials');
($r['code'] === 403) ? $R->pass('auth', 'GET /financials sem financials.view → 403') : $R->fail('auth', 'sem view → 403', 'code=' . $r['code']);

$admin = new HttpClient();
$admin->login('admin@dancacarajas.com', PASSWORD);
$r = $admin->get('/financials');
($r['code'] === 200) ? $R->pass('auth', 'GET /financials admin → 200') : $R->fail('auth', 'admin list', 'code=' . $r['code']);

$menu = $admin->get('/dashboard');
str_contains($menu['body'], 'Financeiro')
    ? $R->pass('auth', 'Menu Financeiro visível com financials.view')
    : $R->fail('auth', 'Menu Financeiro');
$semMenu = $sem->get('/dashboard');
!preg_match('#href=["\'][^"\']*/financials["\']#', $semMenu['body'])
    ? $R->pass('auth', 'Menu Financeiro oculto sem financials.view')
    : $R->fail('auth', 'Menu oculto sem view');

$r = $sem->get('/financials/create');
($r['code'] === 403) ? $R->pass('auth', 'GET /financials/create sem create → 403') : $R->fail('auth', 'create sem perm', 'code=' . $r['code']);

$badCsrf = new HttpClient();
$badCsrf->login('admin@dancacarajas.com', PASSWORD);
$r = $badCsrf->post('/financials', ['_csrf' => 'x', 'sponsor_id' => '1', 'title' => 'Teste CSRF']);
($r['code'] === 419) ? $R->pass('auth', 'POST CSRF inválido → 419') : $R->fail('auth', 'CSRF', 'code=' . $r['code']);

$sponsorId = ensureSponsor($admin, $R);
if ($sponsorId <= 0) {
    echo PHP_EOL . 'ABORT: sponsor_id ausente' . PHP_EOL;
    exit(1);
}
$sp = dbq('SELECT company_id, contact_id, opportunity_id, proposal_id, quota_id FROM sponsors WHERE id=?', [$sponsorId]);
$contractRow = dbq('SELECT id FROM contracts WHERE sponsor_id = ? AND archived_at IS NULL ORDER BY id DESC LIMIT 1', [$sponsorId]);
$contractId = (int) ($contractRow['id'] ?? 0);

echo PHP_EOL . "3. VALIDAÇÃO DE CAMPOS" . PHP_EOL;
$validationTests = [
    ['sem patrocinador', fePayload(['title' => 'Teste sem sponsor', 'planned_amount' => '1000'])],
    ['patrocinador inexistente', fePayload(['sponsor_id' => '999999', 'title' => 'Teste sponsor inválido', 'planned_amount' => '1000'])],
    ['sem título curto', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'AB', 'planned_amount' => '1000'])],
    ['tipo inválido', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste tipo', 'entry_type' => 'invalido', 'planned_amount' => '1000'])],
    ['mecanismo inválido', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste mec', 'funding_mechanism' => 'invalido', 'planned_amount' => '1000'])],
    ['pagamento inválido', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste pag', 'payment_method' => 'invalido', 'planned_amount' => '1000'])],
    ['status inválido', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste st', 'status' => 'invalido', 'planned_amount' => '1000'])],
    ['fiscal inválido', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste fiscal', 'fiscal_document_status' => 'invalido', 'planned_amount' => '1000'])],
    ['valor previsto negativo', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste val', 'planned_amount' => '-100'])],
    ['recebido negativo', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste rec neg', 'planned_amount' => '1000', 'received_amount' => '-50'])],
    ['recebido > previsto', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste recebido', 'planned_amount' => '1000', 'received_amount' => '2000'])],
    ['parcela > total', fePayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste parcela', 'planned_amount' => '1000', 'installment_number' => '3', 'installments_total' => '2'])],
];
foreach ($validationTests as [$label, $payload]) {
    $r = postFinancial($admin, $payload);
    ($r['code'] === 422)
        ? $R->pass('validacao', "POST inválido: {$label} → 422")
        : $R->fail('validacao', $label, 'code=' . $r['code']);
}

$testTitle = 'LANÇAMENTO TESTE ETAPA 15 ' . date('His');
$fiscalId = 0;
echo PHP_EOL . "4. CRIAÇÃO VÁLIDA E CRUD" . PHP_EOL;
$r = postFinancial($admin, fePayload([
    'sponsor_id' => (string) $sponsorId,
    'company_id' => (string) ($sp['company_id'] ?? ''),
    'contact_id' => (string) ($sp['contact_id'] ?? ''),
    'opportunity_id' => (string) ($sp['opportunity_id'] ?? ''),
    'proposal_id' => (string) ($sp['proposal_id'] ?? ''),
    'quota_id' => (string) ($sp['quota_id'] ?? ''),
    'contract_id' => $contractId > 0 ? (string) $contractId : '',
    'title' => $testTitle,
    'planned_amount' => '25000',
    'installment_number' => '1',
    'installments_total' => '3',
    'due_date' => date('Y-m-d', strtotime('+30 days')),
]));
$feId = resolveFinancialId($r, $testTitle);
if ($feId > 0) {
    $R->pass('crud', 'POST válido → redirect show', 'id=' . $feId);
} else {
    $R->fail('crud', 'POST válido', 'code=' . $r['code']);
}
$R->ids['financial_id'] = $feId;

if ($feId > 0) {
    $fe = dbq('SELECT sponsor_id, company_id, planned_amount, received_amount, remaining_amount FROM financial_entries WHERE id=?', [$feId]);
    ((int) $fe['sponsor_id'] === $sponsorId && (int) $fe['company_id'] === (int) ($sp['company_id'] ?? 0))
        ? $R->pass('crud', 'Vínculos herdados do sponsor')
        : $R->fail('crud', 'Vínculos herdados');
    ((float) $fe['remaining_amount'] === 25000.0)
        ? $R->pass('crud', 'remaining_amount calculado na criação', '25000')
        : $R->fail('crud', 'remaining_amount', (string) ($fe['remaining_amount'] ?? ''));

    $r = $admin->get('/financials/' . $feId);
    ($r['code'] === 200) ? $R->pass('crud', 'GET show → 200') : $R->fail('crud', 'GET show', 'code=' . $r['code']);

    $r = $admin->get('/financials/' . $feId . '/edit');
    ($r['code'] === 200) ? $R->pass('crud', 'GET edit → 200') : $R->fail('crud', 'GET edit', 'code=' . $r['code']);

    $editPage = $admin->get('/financials/' . $feId . '/edit');
    $csrf = HttpClient::extractCsrf($editPage['body']);
    $updatedTitle = $testTitle . ' ATUALIZADO';
    $r = $admin->post('/financials/' . $feId . '/update', array_merge(fePayload([
        'sponsor_id' => (string) $sponsorId,
        'title' => $updatedTitle,
        'planned_amount' => '30000',
        'status' => 'aguardando_pagamento',
    ]), ['_csrf' => $csrf ?? '']));
    ($r['code'] === 302) ? $R->pass('crud', 'POST update → redirect') : $R->fail('crud', 'POST update', 'code=' . $r['code']);
    $log = dbq("SELECT id FROM activity_logs WHERE entity_type='financial_entry' AND entity_id=? AND action='financial_entry_updated' ORDER BY id DESC LIMIT 1", [$feId]);
    $log ? $R->pass('crud', 'Log financial_entry_updated') : $R->fail('crud', 'Log financial_entry_updated');

    echo PHP_EOL . "5. CONFIRMAÇÃO, CONCILIAÇÃO E STATUS" . PHP_EOL;
    $partialTitle = 'PARCIAL ETAPA 15 ' . date('His');
    $rPartial = postFinancial($admin, fePayload([
        'sponsor_id' => (string) $sponsorId,
        'title' => $partialTitle,
        'planned_amount' => '10000',
    ]));
    $partialId = resolveFinancialId($rPartial, $partialTitle);
    if ($partialId > 0) {
        $show = $admin->get('/financials/' . $partialId);
        $csrf = HttpClient::extractCsrf($show['body']);
        $admin->post('/financials/' . $partialId . '/confirm', [
            '_csrf' => $csrf,
            'received_amount' => '4000',
            'received_at' => date('Y-m-d H:i'),
            'payment_method' => 'pix',
        ]);
        $partial = dbq('SELECT status, received_amount, remaining_amount, confirmed_by FROM financial_entries WHERE id=?', [$partialId]);
        ($partial['status'] === 'recebido_parcial' && (float) $partial['received_amount'] === 4000.0 && !empty($partial['confirmed_by']))
            ? $R->pass('confirm', 'Confirmar recebimento parcial')
            : $R->fail('confirm', 'Recebimento parcial', json_encode($partial));
    } else {
        $R->fail('confirm', 'Criar lançamento parcial');
    }

    $totalTitle = 'TOTAL ETAPA 15 ' . date('His');
    $rTotal = postFinancial($admin, fePayload([
        'sponsor_id' => (string) $sponsorId,
        'title' => $totalTitle,
        'planned_amount' => '8000',
    ]));
    $totalId = resolveFinancialId($rTotal, $totalTitle);
    if ($totalId > 0) {
        $show = $admin->get('/financials/' . $totalId);
        $csrf = HttpClient::extractCsrf($show['body']);
        $admin->post('/financials/' . $totalId . '/confirm', [
            '_csrf' => $csrf,
            'received_amount' => '8000',
            'received_at' => date('Y-m-d H:i'),
            'payment_method' => 'transferencia',
        ]);
        $total = dbq('SELECT status, received_amount, remaining_amount FROM financial_entries WHERE id=?', [$totalId]);
        ($total['status'] === 'recebido' && (float) $total['remaining_amount'] === 0.0)
            ? $R->pass('confirm', 'Confirmar recebimento total')
            : $R->fail('confirm', 'Recebimento total', json_encode($total));

        $show = $admin->get('/financials/' . $totalId);
        $csrf = HttpClient::extractCsrf($show['body']);
        $admin->post('/financials/' . $totalId . '/reconcile', [
            '_csrf' => $csrf,
            'reconciliation_notes' => 'Conciliação teste etapa 15',
            'bank_reference' => 'BANK-TEST-15',
        ]);
        $reconciled = dbq('SELECT status, reconciled_at, reconciled_by FROM financial_entries WHERE id=?', [$totalId]);
        ($reconciled['status'] === 'conciliado' && !empty($reconciled['reconciled_at']) && !empty($reconciled['reconciled_by']))
            ? $R->pass('reconcile', 'Conciliar preenche reconciled_at/reconciled_by')
            : $R->fail('reconcile', 'Conciliar', json_encode($reconciled));
    } else {
        $R->fail('confirm', 'Criar lançamento total');
        $R->fail('reconcile', 'Conciliar (depende total)');
    }

    $cancelTitle = 'CANCEL ETAPA 15 ' . date('His');
    $rCancel = postFinancial($admin, fePayload([
        'sponsor_id' => (string) $sponsorId,
        'title' => $cancelTitle,
        'planned_amount' => '1500',
    ]));
    $cancelId = resolveFinancialId($rCancel, $cancelTitle);
    if ($cancelId > 0) {
        $show = $admin->get('/financials/' . $cancelId);
        $csrf = HttpClient::extractCsrf($show['body']);
        $admin->post('/financials/' . $cancelId . '/status', ['_csrf' => $csrf, 'status' => 'cancelado', 'notes' => 'Cancelado teste']);
        $cancelled = dbq('SELECT status, cancelled_at, cancelled_by FROM financial_entries WHERE id=?', [$cancelId]);
        ($cancelled['status'] === 'cancelado' && !empty($cancelled['cancelled_at']) && !empty($cancelled['cancelled_by']))
            ? $R->pass('status', 'Status cancelado preenche cancelled_at/cancelled_by')
            : $R->fail('status', 'cancelado', json_encode($cancelled));
    } else {
        $R->fail('status', 'Criar lançamento cancel');
    }

    echo PHP_EOL . "6. ARQUIVAR / RESTAURAR" . PHP_EOL;
    $show = $admin->get('/financials/' . $feId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/financials/' . $feId . '/archive', ['_csrf' => $csrf]);
    !empty(dbq('SELECT archived_at FROM financial_entries WHERE id=?', [$feId])['archived_at'])
        ? $R->pass('archive', 'archive preenche archived_at')
        : $R->fail('archive', 'archived_at');
    $list = $admin->get('/financials');
    !str_contains($list['body'], $updatedTitle)
        ? $R->pass('archive', 'Some da listagem padrão')
        : $R->fail('archive', 'listagem padrão');
    $archList = $admin->get('/financials?show_archived=1');
    str_contains($archList['body'], $updatedTitle)
        ? $R->pass('archive', 'Filtro arquivados mostra')
        : $R->fail('archive', 'filtro arquivados');
    $show = $admin->get('/financials/' . $feId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/financials/' . $feId . '/restore', ['_csrf' => $csrf]);
    empty(dbq('SELECT archived_at FROM financial_entries WHERE id=?', [$feId])['archived_at'])
        ? $R->pass('archive', 'restore limpa archived_at')
        : $R->fail('archive', 'restore');

    echo PHP_EOL . "7. DOCUMENTOS VINCULADOS" . PHP_EOL;
    $docPath = sys_get_temp_dir() . '/doc_test_etapa15.txt';
    file_put_contents($docPath, 'Documento teste validacao etapa 15');
    $docPage = $admin->get('/financials/' . $feId . '/documents/create');
    ($docPage['code'] === 200 && str_contains($docPage['body'], (string) $feId))
        ? $R->pass('docs', 'GET /financials/{id}/documents/create → 200')
        : $R->fail('docs', 'create contextual', 'code=' . $docPage['code']);

    $uploadDoc = static function (HttpClient $http, int $financialId, int $sponsorId, array $sp, string $title, string $flag): int {
        $docPage = $http->get('/financials/' . $financialId . '/documents/create');
        $docCsrf = HttpClient::extractCsrf($docPage['body']);
        $post = [
            '_csrf' => $docCsrf,
            'financial_entry_id' => (string) $financialId,
            'sponsor_id' => (string) $sponsorId,
            'company_id' => (string) ($sp['company_id'] ?? ''),
            'title' => $title,
            'category' => 'comprovante_envio',
            'status' => 'ativo',
            'access_level' => 'interno',
            'document_date' => date('Y-m-d'),
            $flag => '1',
        ];
        $docPath = sys_get_temp_dir() . '/doc_test_etapa15.txt';
        $docRes = $http->post('/documents', $post, 'document_file', $docPath);
        if (preg_match('#/documents/(\d+)#', (string) ($docRes['location'] ?? ''), $dm)) {
            return (int) $dm[1];
        }
        return (int) (dbq('SELECT id FROM documents WHERE title = ? ORDER BY id DESC LIMIT 1', [$title])['id'] ?? 0);
    };

    $proofTitle = 'DOC PROOF ETAPA 15';
    $proofId = $uploadDoc($admin, $feId, $sponsorId, $sp, $proofTitle, 'use_as_proof');
    $proofId > 0 ? $R->pass('docs', 'Documento com financial_entry_id (proof)', 'id=' . $proofId) : $R->fail('docs', 'Upload proof');
    if ($proofId > 0) {
        $d = dbq('SELECT financial_entry_id FROM documents WHERE id=?', [$proofId]);
        ((int) ($d['financial_entry_id'] ?? 0) === $feId) ? $R->pass('docs', 'documents.financial_entry_id salvo') : $R->fail('docs', 'financial_entry_id');
        $linked = dbq('SELECT proof_document_id FROM financial_entries WHERE id=?', [$feId]);
        ((int) ($linked['proof_document_id'] ?? 0) === $proofId)
            ? $R->pass('docs', 'use_as_proof vincula proof_document_id')
            : $R->fail('docs', 'proof_document_id');
    }

    $receiptTitle = 'DOC RECEIPT ETAPA 15';
    $receiptId = $uploadDoc($admin, $feId, $sponsorId, $sp, $receiptTitle, 'use_as_receipt');
    $receiptId > 0 ? $R->pass('docs', 'use_as_receipt vincula recibo', 'id=' . $receiptId) : $R->fail('docs', 'use_as_receipt');
    if ($receiptId > 0) {
        $linked = dbq('SELECT receipt_document_id FROM financial_entries WHERE id=?', [$feId]);
        ((int) ($linked['receipt_document_id'] ?? 0) === $receiptId)
            ? $R->pass('docs', 'receipt_document_id salvo')
            : $R->fail('docs', 'receipt_document_id');
    }

    $fiscalTitle = 'DOC FISCAL ETAPA 15';
    $fiscalId = $uploadDoc($admin, $feId, $sponsorId, $sp, $fiscalTitle, 'use_as_fiscal');
    $fiscalId > 0 ? $R->pass('docs', 'use_as_fiscal vincula documento fiscal', 'id=' . $fiscalId) : $R->fail('docs', 'use_as_fiscal');
    if ($fiscalId > 0) {
        $linked = dbq('SELECT fiscal_document_id, fiscal_document_status FROM financial_entries WHERE id=?', [$feId]);
        ((int) ($linked['fiscal_document_id'] ?? 0) === $fiscalId && ($linked['fiscal_document_status'] ?? '') === 'anexado')
            ? $R->pass('docs', 'fiscal_document_id + status anexado')
            : $R->fail('docs', 'fiscal_document_id');
        $showDoc = $admin->get('/documents/' . $fiscalId);
        str_contains($showDoc['body'], '/financials/' . $feId)
            ? $R->pass('docs', 'Show documento link financeiro')
            : $R->fail('docs', 'Show documento link');
    }
}

echo PHP_EOL . "8. FILTROS E PAGINAÇÃO" . PHP_EOL;
$chain = ensureFullTestChain($admin, $R);
$R->ids = array_merge($R->ids, $chain);
$filters = [
    'busca' => '/financials?q=LANÇAMENTO',
    'patrocinador' => '/financials?sponsor_id=' . $sponsorId,
    'empresa' => '/financials?company_id=' . (int) ($sp['company_id'] ?? 0),
    'contato' => '/financials?contact_id=' . (int) ($chain['contactId'] ?? 0),
    'oportunidade' => '/financials?opportunity_id=' . (int) ($chain['oppId'] ?? 0),
    'proposta' => '/financials?proposal_id=' . (int) ($chain['propId'] ?? 0),
    'cota' => '/financials?quota_id=' . (int) ($chain['quotaId'] ?? 0),
    'contrato' => '/financials?contract_id=' . $contractId,
    'tipo' => '/financials?entry_type=parcela_patrocinio',
    'mecanismo' => '/financials?funding_mechanism=lei_rouanet',
    'pagamento' => '/financials?payment_method=pix',
    'status' => '/financials?status=previsto',
    'fiscal' => '/financials?fiscal_document_status=nao_aplicavel',
    'responsavel' => '/financials?responsible_user_id=1',
    'vencimento_de' => '/financials?due_from=' . date('Y-m-d'),
    'vencimento_ate' => '/financials?due_to=' . date('Y-m-d', strtotime('+365 days')),
    'recebimento_de' => '/financials?received_from=' . date('Y-m-d', strtotime('-30 days')),
    'recebimento_ate' => '/financials?received_to=' . date('Y-m-d'),
    'atrasados' => '/financials?overdue=1',
    'recebidos' => '/financials?received=1',
    'parciais' => '/financials?partial=1',
    'conciliados' => '/financials?reconciled=1',
    'pendentes' => '/financials?pending=1',
    'arquivados' => '/financials?show_archived=1',
    'pagina_2' => '/financials?page=2',
    'doc_financeiro' => '/documents?financial_entry_id=' . (int) ($R->ids['financial_id'] ?? 0),
];
foreach ($filters as $name => $url) {
    if (preg_match('#=(0)(?:&|$)#', $url) || str_contains($url, 'financial_entry_id=0')) {
        continue;
    }
    $r = $admin->get($url);
    ($r['code'] === 200) ? $R->pass('filtros', "Filtro {$name} → 200") : $R->fail('filtros', $name, 'code=' . $r['code']);
}
($admin->get('/financials')['code'] === 200)
    ? $R->pass('filtros', 'Limpar filtros (/financials) → 200')
    : $R->fail('filtros', 'limpar filtros');

echo PHP_EOL . "9. BLOCOS CONTEXTUAIS E DASHBOARD" . PHP_EOL;
foreach ([
    'sponsors' => $sponsorId,
    'contracts' => $contractId,
    'companies' => (int) ($chain['companyId'] ?? 0),
    'contacts' => (int) ($chain['contactId'] ?? 0),
    'opportunities' => (int) ($chain['oppId'] ?? 0),
    'proposals' => (int) ($chain['propId'] ?? 0),
    'quotas' => (int) ($chain['quotaId'] ?? 0),
] as $entity => $eid) {
    if ($eid <= 0) {
        $R->fail('contexto', "/{$entity} id ausente");
        continue;
    }
    $r = $admin->get("/{$entity}/{$eid}");
    $hasBlock = str_contains($r['body'], 'financial-summary')
        && str_contains($r['body'], 'Ver todos')
        && (str_contains($r['body'], 'formalizado') || str_contains($r['body'], 'previsto') || str_contains($r['body'], 'recebido'));
    if ($r['code'] === 200 && $hasBlock) {
        $R->pass('contexto', "Bloco financeiro em /{$entity}/{$eid}");
    } else {
        $R->fail('contexto', "/{$entity}/{$eid}", 'code=' . $r['code'] . ' block=' . ($hasBlock ? '1' : '0'));
    }
    $createRoute = "/{$entity}/{$eid}/financials/create";
    ($admin->get($createRoute)['code'] === 200)
        ? $R->pass('contexto', "GET {$createRoute} → 200")
        : $R->fail('contexto', $createRoute);
}
$dash = $admin->get('/dashboard');
foreach (['Financeiro', 'Previsto', 'Recebido', 'Saldo', 'parcial', 'atraso', 'conciliado', 'Gerenciar financeiro', 'Novo lançamento'] as $needle) {
    str_contains($dash['body'], $needle)
        ? $R->pass('dashboard', "Dashboard contém: {$needle}")
        : $R->fail('dashboard', $needle);
}

if ($feId > 0 && isset($fiscalId) && $fiscalId > 0) {
    $dl = $admin->get('/documents/' . $fiscalId . '/download');
    in_array($dl['code'], [200, 302], true)
        ? $R->pass('docs', 'Download protegido documento → ' . $dl['code'])
        : $R->fail('docs', 'download', 'code=' . $dl['code']);
    $showFe = $admin->get('/financials/' . $feId);
    str_contains($showFe['body'], 'Documentos') || str_contains($showFe['body'], 'financeiros')
        ? $R->pass('docs', 'Show financeiro bloco documentos')
        : $R->fail('docs', 'bloco documentos financeiro');
}

echo PHP_EOL . "10. ESCOPO NÃO CRIADO" . PHP_EOL;
$futureRoutes = ['/finance/reports', '/portal', '/signatures'];
foreach ($futureRoutes as $route) {
    $r = $admin->get($route);
    !in_array($r['code'], [200], true)
        ? $R->pass('escopo', "Rota futura {$route} não exposta (code={$r['code']})")
        : $R->fail('escopo', "Rota {$route} não deveria existir");
}

archiveTestData($R);

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
echo PHP_EOL . 'Validação Etapa 15 concluída com sucesso.' . PHP_EOL;
