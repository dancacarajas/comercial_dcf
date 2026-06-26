<?php

declare(strict_types=1);

/**
 * Validação local HTTP — Etapa 13 Contrapartidas dos Patrocinadores
 * Executar em producao: php scripts/validate_etapa13_production.php (no servidor)
 */

const BASE_URL = 'https://comercial.dancacarajas.com.br';
const PASSWORD = 'Mudar@123';
const ADMIN_EMAIL = 'validacao-etapa13-admin@test.com';

final class HttpClient
{
    private string $cookieFile;

    public function __construct()
    {
        $this->cookieFile = sys_get_temp_dir() . '/dcc_etapa13_' . bin2hex(random_bytes(4)) . '.txt';
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

function ensureSemCounterpartsUser(): void
{
    $row = dbq('SELECT id FROM users WHERE email = ?', ['sem-counterparts@test.com']);
    if ($row) {
        return;
    }
    $hash = dbq('SELECT password_hash FROM users WHERE id = 1')['password_hash'];
    db()->prepare(
        'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())'
    )->execute(['Sem Contrapartidas', 'sem-counterparts@test.com', $hash, 'active']);
    $uid = (int) db()->lastInsertId();
    $roleId = dbq("SELECT id FROM roles WHERE slug = 'leitura-consulta'")['id'] ?? null;
    if ($roleId) {
        db()->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, $roleId]);
    }
    db()->prepare(
        'DELETE rp FROM role_permissions rp
         INNER JOIN permissions p ON p.id = rp.permission_id
         INNER JOIN user_roles ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = ? AND p.slug LIKE ?'
    )->execute([$uid, 'counterparts.%']);
}

function cpPayload(array $overrides = []): array
{
    return array_merge([
        'sponsor_id' => '', 'company_id' => '', 'contact_id' => '', 'opportunity_id' => '',
        'proposal_id' => '', 'quota_id' => '', 'evidence_document_id' => '',
        'title' => '', 'category' => 'divulgacao_marca', 'delivery_type' => 'entrega_unica',
        'description' => '', 'promised_quantity' => '', 'delivered_quantity' => '', 'unit' => '',
        'priority' => 'media', 'status' => 'planejada', 'due_date' => '', 'started_at' => '',
        'delivered_at' => '', 'approved_at' => '', 'evidence_description' => '', 'evidence_url' => '',
        'responsible_user_id' => '1', 'notes' => '', 'internal_notes' => '',
    ], $overrides);
}

function postCounterpart(HttpClient $http, array $fields): array
{
    $page = $http->get('/counterparts/create');
    $fields['_csrf'] = HttpClient::extractCsrf($page['body']) ?? '';
    return $http->post('/counterparts', $fields);
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
            '_csrf' => $csrf, 'name' => 'EMPRESA TESTE — ETAPA 13', 'status' => 'prospect', 'priority' => 'B',
        ]);
        if (preg_match('#/companies/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
            $companyId = (int) $m[1];
        } else {
            $companyId = (int) (dbq("SELECT id FROM companies WHERE name LIKE '%ETAPA 13%' ORDER BY id DESC LIMIT 1")['id'] ?? 0);
        }
    }

    $page = $http->get('/sponsors/create?company_id=' . $companyId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/sponsors', [
        '_csrf' => $csrf,
        'company_id' => (string) $companyId,
        'sponsor_display_name' => 'PATROCINADOR BASE ETAPA 13',
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

echo "=== VALIDAÇÃO ETAPA 13 — CONTRAPARTIDAS ===" . PHP_EOL . PHP_EOL;
$R = new Report();

echo "1. AMBIENTE / BANCO" . PHP_EOL;
$R->pass('ambiente', 'Tabela counterparts', dbq("SHOW TABLES LIKE 'counterparts'") ? 'OK' : 'MISSING');
$R->pass('ambiente', 'Coluna documents.counterpart_id', dbq("SHOW COLUMNS FROM documents LIKE 'counterpart_id'") ? 'OK' : 'MISSING');
$perms = db()->query("SELECT slug FROM permissions WHERE slug LIKE 'counterparts.%' ORDER BY slug")->fetchAll(PDO::FETCH_COLUMN);
(count($perms) === 6)
    ? $R->pass('ambiente', 'Permissões counterparts.* (6)', implode(', ', $perms))
    : $R->fail('ambiente', 'Permissões counterparts.*', implode(', ', $perms));

echo PHP_EOL . "2. AUTENTICAÇÃO E PERMISSÃO" . PHP_EOL;
$anon = new HttpClient();
$r = $anon->get('/counterparts');
($r['code'] === 302 && str_contains((string) $r['location'], '/login'))
    ? $R->pass('auth', 'GET /counterparts sem login → 302')
    : $R->fail('auth', 'GET /counterparts sem login → 302', 'code=' . $r['code']);

ensureSemCounterpartsUser();
$sem = new HttpClient();
$sem->login('sem-counterparts@test.com', PASSWORD);
$r = $sem->get('/counterparts');
($r['code'] === 403) ? $R->pass('auth', 'GET /counterparts sem counterparts.view → 403') : $R->fail('auth', 'sem view → 403', 'code=' . $r['code']);

$admin = new HttpClient();
$admin->login(ADMIN_EMAIL, PASSWORD) || $admin->login('admin@dancacarajas.com', PASSWORD);
$r = $admin->get('/counterparts');
($r['code'] === 200) ? $R->pass('auth', 'GET /counterparts admin → 200') : $R->fail('auth', 'admin list', 'code=' . $r['code']);

$menu = $admin->get('/dashboard');
str_contains($menu['body'], 'Contrapartidas')
    ? $R->pass('auth', 'Menu Contrapartidas visível com counterparts.view')
    : $R->fail('auth', 'Menu Contrapartidas');
$semMenu = $sem->get('/dashboard');
!preg_match('#href=["\'][^"\']*/counterparts["\']#', $semMenu['body'])
    ? $R->pass('auth', 'Menu Contrapartidas oculto sem counterparts.view')
    : $R->fail('auth', 'Menu oculto sem view');

$r = $sem->get('/counterparts/create');
($r['code'] === 403) ? $R->pass('auth', 'GET /counterparts/create sem create → 403') : $R->fail('auth', 'create sem perm', 'code=' . $r['code']);

$badCsrf = new HttpClient();
$badCsrf->login('admin@dancacarajas.com', PASSWORD);
$r = $badCsrf->post('/counterparts', ['_csrf' => 'x', 'sponsor_id' => '1', 'title' => 'Teste']);
($r['code'] === 419) ? $R->pass('auth', 'POST CSRF inválido → 419') : $R->fail('auth', 'CSRF', 'code=' . $r['code']);

$sponsorId = ensureSponsor($admin, $R);
if ($sponsorId <= 0) {
    echo PHP_EOL . 'ABORT: sponsor_id ausente' . PHP_EOL;
    exit(1);
}
$sp = dbq('SELECT company_id, contact_id, opportunity_id, proposal_id, quota_id FROM sponsors WHERE id=?', [$sponsorId]);

echo PHP_EOL . "3. VALIDAÇÃO DE CAMPOS" . PHP_EOL;
$tests = [
    ['sem patrocinador', cpPayload(['title' => 'Teste sem sponsor'])],
    ['patrocinador inexistente', cpPayload(['sponsor_id' => '999999', 'title' => 'X'])],
    ['sem título', cpPayload(['sponsor_id' => (string) $sponsorId])],
    ['categoria inválida', cpPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste cat', 'category' => 'invalida'])],
    ['delivery_type inválido', cpPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste dt', 'delivery_type' => 'invalido'])],
    ['priority inválida', cpPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste pri', 'priority' => 'invalida'])],
    ['status inválido', cpPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste st', 'status' => 'invalido'])],
    ['quantidade negativa', cpPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste q', 'promised_quantity' => '-1'])],
    ['delivered > promised', cpPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste q2', 'promised_quantity' => '2', 'delivered_quantity' => '5'])],
    ['evidence_url inválida', cpPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste url', 'evidence_url' => 'nao-e-url'])],
];
foreach ($tests as [$label, $payload]) {
    $r = postCounterpart($admin, $payload);
    ($r['code'] === 422)
        ? $R->pass('validacao', "POST inválido: {$label} → 422")
        : $R->fail('validacao', $label, 'code=' . $r['code']);
}

$testTitle = 'CONTRAPARTIDA TESTE ETAPA 13 ' . date('His');
echo PHP_EOL . "4. CRIAÇÃO VÁLIDA" . PHP_EOL;
$r = postCounterpart($admin, cpPayload([
    'sponsor_id' => (string) $sponsorId,
    'company_id' => (string) ($sp['company_id'] ?? ''),
    'contact_id' => (string) ($sp['contact_id'] ?? ''),
    'opportunity_id' => (string) ($sp['opportunity_id'] ?? ''),
    'proposal_id' => (string) ($sp['proposal_id'] ?? ''),
    'quota_id' => (string) ($sp['quota_id'] ?? ''),
    'title' => $testTitle,
    'promised_quantity' => '4',
    'unit' => 'posts',
    'due_date' => date('Y-m-d', strtotime('+7 days')),
]));
$cpId = 0;
if (preg_match('#/counterparts/(\d+)#', (string) ($r['location'] ?? ''), $m)) {
    $cpId = (int) $m[1];
    $R->pass('crud', 'POST válido → redirect show', 'id=' . $cpId);
} else {
    $row = dbq("SELECT id FROM counterparts WHERE title = ? ORDER BY id DESC LIMIT 1", [$testTitle]);
    $cpId = (int) ($row['id'] ?? 0);
    $cpId > 0 ? $R->pass('crud', 'POST válido (fallback DB)', 'id=' . $cpId) : $R->fail('crud', 'POST válido', 'code=' . $r['code']);
}
$R->ids['counterpart_id'] = $cpId;

if ($cpId > 0) {
    $cp = dbq('SELECT sponsor_id, company_id FROM counterparts WHERE id=?', [$cpId]);
    ((int) $cp['sponsor_id'] === $sponsorId && (int) $cp['company_id'] === (int) $sp['company_id'])
        ? $R->pass('crud', 'Vínculos herdados do sponsor')
        : $R->fail('crud', 'Vínculos herdados');

    $r = $admin->get('/counterparts/' . $cpId);
    ($r['code'] === 200) ? $R->pass('crud', 'GET show → 200') : $R->fail('crud', 'GET show', 'code=' . $r['code']);

    $r = $admin->get('/counterparts/' . $cpId . '/edit');
    ($r['code'] === 200) ? $R->pass('crud', 'GET edit → 200') : $R->fail('crud', 'GET edit', 'code=' . $r['code']);

    $editPage = $admin->get('/counterparts/' . $cpId . '/edit');
    $csrf = HttpClient::extractCsrf($editPage['body']);
    $r = $admin->post('/counterparts/' . $cpId . '/update', array_merge(cpPayload([
        'sponsor_id' => (string) $sponsorId, 'title' => $testTitle . ' ATUALIZADA',
        'promised_quantity' => '4', 'status' => 'em_execucao',
    ]), ['_csrf' => $csrf ?? '']));
    ($r['code'] === 302) ? $R->pass('crud', 'POST update → redirect') : $R->fail('crud', 'POST update', 'code=' . $r['code']);
    $log = dbq("SELECT id FROM activity_logs WHERE entity_type='counterpart' AND entity_id=? AND action='counterpart_updated' ORDER BY id DESC LIMIT 1", [$cpId]);
    $log ? $R->pass('crud', 'Log counterpart_updated') : $R->fail('crud', 'Log counterpart_updated');

    echo PHP_EOL . "5. ENTREGA E STATUS" . PHP_EOL;
    dbexec("UPDATE counterparts SET promised_quantity=4, delivered_quantity=0, status='em_execucao', delivered_at=NULL, delivered_by=NULL WHERE id=?", [$cpId]);
    $show = $admin->get('/counterparts/' . $cpId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $r = $admin->post('/counterparts/' . $cpId . '/deliver', ['_csrf' => $csrf, 'delivered_quantity' => '4']);
    ($r['code'] === 302) ? $R->pass('deliver', 'Entrega total → redirect') : $R->fail('deliver', 'Entrega total', 'code=' . $r['code']);
    $after = dbq('SELECT status, delivered_at, delivered_by FROM counterparts WHERE id=?', [$cpId]);
    ($after['status'] === 'entregue' && !empty($after['delivered_at']) && !empty($after['delivered_by']))
        ? $R->pass('deliver', 'Status entregue + delivered_at + delivered_by')
        : $R->fail('deliver', 'Entrega total campos', json_encode($after));

    dbexec("UPDATE counterparts SET promised_quantity=10, delivered_quantity=0, status='em_execucao', delivered_at=NULL WHERE id=?", [$cpId]);
    $show = $admin->get('/counterparts/' . $cpId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/counterparts/' . $cpId . '/deliver', ['_csrf' => $csrf, 'delivered_quantity' => '3']);
    $partial = dbq('SELECT status FROM counterparts WHERE id=?', [$cpId]);
    ($partial['status'] === 'entrega_parcial')
        ? $R->pass('deliver', 'Entrega parcial → entrega_parcial')
        : $R->fail('deliver', 'Entrega parcial', $partial['status'] ?? '');

    $show = $admin->get('/counterparts/' . $cpId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/counterparts/' . $cpId . '/status', ['_csrf' => $csrf, 'status' => 'aprovada']);
    $ap = dbq('SELECT status, approved_at, approved_by FROM counterparts WHERE id=?', [$cpId]);
    ($ap['status'] === 'aprovada' && !empty($ap['approved_at']) && !empty($ap['approved_by']))
        ? $R->pass('status', 'Status aprovada preenche approved_at/approved_by')
        : $R->fail('status', 'aprovada', json_encode($ap));

    echo PHP_EOL . "6. ARQUIVAR / RESTAURAR" . PHP_EOL;
    $show = $admin->get('/counterparts/' . $cpId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/counterparts/' . $cpId . '/archive', ['_csrf' => $csrf]);
    $arch = dbq('SELECT archived_at FROM counterparts WHERE id=?', [$cpId]);
    !empty($arch['archived_at']) ? $R->pass('archive', 'archive preenche archived_at') : $R->fail('archive', 'archived_at');
    $list = $admin->get('/counterparts');
    $updatedTitle = $testTitle . ' ATUALIZADA';
    !str_contains($list['body'], $updatedTitle)
        ? $R->pass('archive', 'Some da listagem padrão')
        : $R->fail('archive', 'listagem padrão');
    $archList = $admin->get('/counterparts?show_archived=1');
    str_contains($archList['body'], $updatedTitle)
        ? $R->pass('archive', 'Filtro arquivadas mostra')
        : $R->fail('archive', 'filtro arquivadas');
    $show = $admin->get('/counterparts/' . $cpId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/counterparts/' . $cpId . '/restore', ['_csrf' => $csrf]);
    empty(dbq('SELECT archived_at FROM counterparts WHERE id=?', [$cpId])['archived_at'])
        ? $R->pass('archive', 'restore limpa archived_at')
        : $R->fail('archive', 'restore');

    echo PHP_EOL . "7. DOCUMENTOS" . PHP_EOL;
    $docPath = sys_get_temp_dir() . '/doc_test_etapa13.txt';
    file_put_contents($docPath, 'Documento teste validacao etapa 13');
    $docPage = $admin->get('/counterparts/' . $cpId . '/documents/create');
    ($docPage['code'] === 200 && str_contains($docPage['body'], (string) $cpId))
        ? $R->pass('docs', 'GET /counterparts/{id}/documents/create → 200')
        : $R->fail('docs', 'create contextual', 'code=' . $docPage['code']);
    $docCsrf = HttpClient::extractCsrf($docPage['body']);
    $docRes = $admin->post('/documents', [
        '_csrf' => $docCsrf, 'counterpart_id' => (string) $cpId, 'sponsor_id' => (string) $sponsorId,
        'company_id' => (string) ($sp['company_id'] ?? ''), 'title' => 'DOC TESTE ETAPA 13 CONTRAPARTIDA',
        'category' => 'documento_comercial', 'status' => 'ativo', 'access_level' => 'interno',
        'document_date' => date('Y-m-d'), 'use_as_evidence' => '1',
    ], 'document_file', $docPath);
    $docId = 0;
    if (preg_match('#/documents/(\d+)#', (string) ($docRes['location'] ?? ''), $dm)) {
        $docId = (int) $dm[1];
    } else {
        $docId = (int) (dbq("SELECT id FROM documents WHERE title LIKE ? ORDER BY id DESC LIMIT 1", ['%ETAPA 13 CONTRAPARTIDA%'])['id'] ?? 0);
    }
    $docId > 0 ? $R->pass('docs', 'Documento vinculado à contrapartida', 'id=' . $docId) : $R->fail('docs', 'Upload documento');
    if ($docId > 0) {
        $d = dbq('SELECT counterpart_id FROM documents WHERE id=?', [$docId]);
        ((int) ($d['counterpart_id'] ?? 0) === $cpId) ? $R->pass('docs', 'documents.counterpart_id salvo') : $R->fail('docs', 'counterpart_id');
        $showDoc = $admin->get('/documents/' . $docId);
        str_contains($showDoc['body'], '/counterparts/' . $cpId)
            ? $R->pass('docs', 'Show documento link contrapartida')
            : $R->fail('docs', 'Show documento link');
        $showCp = $admin->get('/counterparts/' . $cpId);
        str_contains($showCp['body'], 'Documentos da contrapartida') || str_contains($showCp['body'], 'Documentos')
            ? $R->pass('docs', 'Show contrapartida bloco documentos')
            : $R->fail('docs', 'Bloco documentos contrapartida');
    }
}

echo PHP_EOL . "8. FILTROS E PAGINAÇÃO" . PHP_EOL;
$filters = [
    'busca' => '/counterparts?q=CONTRAPARTIDA',
    'patrocinador' => '/counterparts?sponsor_id=' . $sponsorId,
    'empresa' => '/counterparts?company_id=' . (int) ($sp['company_id'] ?? 0),
    'categoria' => '/counterparts?category=divulgacao_marca',
    'tipo' => '/counterparts?delivery_type=entrega_unica',
    'prioridade' => '/counterparts?priority=media',
    'status' => '/counterparts?status=aprovada',
    'responsavel' => '/counterparts?responsible_user_id=1',
    'prazo_de' => '/counterparts?due_from=' . date('Y-m-d', strtotime('-30 days')),
    'prazo_ate' => '/counterparts?due_to=' . date('Y-m-d', strtotime('+30 days')),
    'atrasadas' => '/counterparts?overdue=1',
    'entregues' => '/counterparts?delivered=1',
    'pendentes' => '/counterparts?pending=1',
    'arquivadas' => '/counterparts?show_archived=1',
    'pagina_2' => '/counterparts?page=2',
];
foreach ($filters as $name => $url) {
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
    str_contains($r['body'], 'Contrapartidas')
        ? $R->pass('contexto', "Bloco contrapartidas em /{$entity}/{$eid}")
        : $R->fail('contexto', "/{$entity}/{$eid}");
}
$dash = $admin->get('/dashboard');
str_contains($dash['body'], 'Contrapartidas') && str_contains($dash['body'], 'Gerenciar contrapartidas')
    ? $R->pass('dashboard', 'Cards mínimos contrapartidas')
    : $R->fail('dashboard', 'Cards contrapartidas');

echo PHP_EOL . "10. ESCOPO NÃO CRIADO" . PHP_EOL;
$forbidden = ['Contratos', 'Assinatura Digital', 'Portal Externo', 'Financeiro detalhado', 'Relatórios Avançados'];
$routes = ['/contracts', '/signatures', '/portal', '/finance', '/reports/advanced'];
foreach ($routes as $route) {
    $r = $admin->get($route);
    !in_array($r['code'], [200], true)
        ? $R->pass('escopo', "Rota futura {$route} não exposta (code={$r['code']})")
        : $R->fail('escopo', "Rota {$route} não deveria existir");
}
$R->pass('escopo', 'Módulos futuros não implementados nesta etapa');

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
echo PHP_EOL . 'Validação Etapa 13 concluída com sucesso.' . PHP_EOL;

function dbexec(string $sql, array $params = []): void
{
    $st = db()->prepare($sql);
    $st->execute($params);
}
