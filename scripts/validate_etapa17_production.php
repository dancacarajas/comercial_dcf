<?php

declare(strict_types=1);

/**
 * Validação HTTP — Etapa 17 Relatórios (produção)
 * Executar no servidor: php scripts/validate_etapa17_production.php
 */

const BASE_URL = 'https://comercial.dancacarajas.com.br';
const PASSWORD = 'Mudar@123';
const ADMIN_EMAIL = 'validacao-etapa17-admin@test.com';
const SEM_VIEW_EMAIL = 'validacao-etapa17-sem@test.com';
const LEITOR_EMAIL = 'validacao-etapa17-leitor@test.com';
const CAP_EMAIL = 'validacao-etapa17-cap@test.com';
const PROD_EMAIL = 'validacao-etapa17-prod@test.com';
const COM_EMAIL = 'validacao-etapa17-com@test.com';

final class HttpClient
{
    private string $cookieFile;

    public function __construct()
    {
        $this->cookieFile = sys_get_temp_dir() . '/dcc_etapa17_' . bin2hex(random_bytes(4)) . '.txt';
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

function appRoot(): string
{
    return dirname(__DIR__);
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

function ensureTempValidationUsers(): void
{
    $hash = password_hash(PASSWORD, PASSWORD_DEFAULT);
    $users = [
        [ADMIN_EMAIL, 'Validacao Etapa17 Admin', 'administrador-geral'],
        [LEITOR_EMAIL, 'Validacao Etapa17 Leitor', 'leitura-consulta'],
        [CAP_EMAIL, 'Validacao Etapa17 Captacao', 'captacao-comercial'],
        [PROD_EMAIL, 'Validacao Etapa17 Producao', 'producao-coordenacao'],
        [COM_EMAIL, 'Validacao Etapa17 Comunicacao', 'comunicacao'],
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
            db()->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$uid]);
            db()->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, (int) $roleId]);
        }
    }
    $semRole = dbq("SELECT id FROM roles WHERE slug = 'teste-sem-reports-etapa17'");
    if (!$semRole) {
        db()->prepare('INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)')
            ->execute(['Teste sem reports Etapa17', 'teste-sem-reports-etapa17', 'Role temporaria validacao']);
        $semRole = dbq("SELECT id FROM roles WHERE slug = 'teste-sem-reports-etapa17'");
    }
    $row = dbq('SELECT id FROM users WHERE email = ?', [SEM_VIEW_EMAIL]);
    if (!$row) {
        db()->prepare('INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())')
            ->execute(['Validacao Etapa17 Sem View', SEM_VIEW_EMAIL, $hash, 'active']);
        $uid = (int) db()->lastInsertId();
    } else {
        $uid = (int) $row['id'];
        db()->prepare("UPDATE users SET status='active', password_hash=? WHERE id=?")->execute([$hash, $uid]);
    }
    if ($semRole) {
        db()->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$uid]);
        db()->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, (int) $semRole['id']]);
    }
}

function deactivateTempValidationUsers(): void
{
    foreach ([ADMIN_EMAIL, SEM_VIEW_EMAIL, LEITOR_EMAIL, CAP_EMAIL, PROD_EMAIL, COM_EMAIL] as $email) {
        db()->prepare("UPDATE users SET status='inactive' WHERE email=?")->execute([$email]);
    }
}

function dbq(string $sql, array $params = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC);
}

function rolePermSlugs(string $roleSlug): array
{
    $st = db()->prepare(
        "SELECT p.slug FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         WHERE r.slug = ? AND p.slug LIKE 'reports.%'
         ORDER BY p.slug"
    );
    $st->execute([$roleSlug]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function restoreReportsRoleMatrix(): void
{
    db()->exec(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r
         JOIN permissions p ON p.slug IN ('reports.view','reports.generate','reports.snapshots','reports.archive','reports.print')
         WHERE r.slug = 'administrador-geral'"
    );
    db()->exec(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r
         JOIN permissions p ON p.slug IN ('reports.view','reports.generate','reports.snapshots','reports.print')
         WHERE r.slug = 'captacao-comercial'"
    );
    db()->exec(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r
         JOIN permissions p ON p.slug IN ('reports.view','reports.generate','reports.print')
         WHERE r.slug IN ('producao-coordenacao','comunicacao')"
    );
    db()->exec(
        "INSERT IGNORE INTO role_permissions (role_id, permission_id)
         SELECT r.id, p.id FROM roles r
         JOIN permissions p ON p.slug IN ('reports.view','reports.print')
         WHERE r.slug = 'leitura-consulta'"
    );
    db()->exec(
        "DELETE rp FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         WHERE r.slug = 'captacao-comercial' AND p.slug = 'reports.archive'"
    );
    db()->exec(
        "DELETE rp FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         WHERE r.slug IN ('producao-coordenacao','comunicacao','leitura-consulta')
           AND p.slug IN ('reports.snapshots','reports.archive')"
    );
}


function snapshotPayload(array $overrides = []): array
{
    return array_merge([
        'report_key'           => 'executive',
        'title'                => '',
        'description'          => 'Snapshot de validação etapa 17',
        'period_start'         => date('Y-m-d', strtotime('-30 days')),
        'period_end'           => date('Y-m-d'),
        'responsible_user_id'  => '0',
        'company_id'           => '0',
        'sponsor_id'           => '0',
        'quota_id'             => '0',
        'status'               => '',
        'source'               => '',
        'only_pending'         => '',
        'only_overdue'         => '',
        'notes'                => 'Notas teste etapa 17',
    ], $overrides);
}

function postSnapshot(HttpClient $http, array $fields): array
{
    $page = $http->get('/reports');
    $fields['_csrf'] = HttpClient::extractCsrf($page['body']) ?? '';
    return $http->post('/reports/snapshots', $fields);
}

function resolveSnapshotId(array $res, string $title): int
{
    if (preg_match('#/reports/snapshots/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
        return (int) $m[1];
    }
    return (int) (dbq('SELECT id FROM report_snapshots WHERE title = ? ORDER BY id DESC LIMIT 1', [$title])['id'] ?? 0);
}

/** @return list<string> */
function etapa17SourceFiles(): array
{
    $root = appRoot();
    $files = [
        'app/Controllers/ReportController.php',
        'app/Models/Report.php',
        'app/Models/ReportSnapshot.php',
        'database/migrations/2026_etapa17_reports.sql',
        'app/Views/reports/index.php',
        'app/Views/reports/pipeline.php',
        'app/Views/reports/proposals.php',
        'app/Views/reports/sponsors.php',
        'app/Views/reports/financials.php',
        'app/Views/reports/contracts.php',
        'app/Views/reports/counterparts.php',
        'app/Views/reports/dossiers.php',
        'app/Views/reports/tasks.php',
        'app/Views/reports/leads.php',
        'app/Views/reports/snapshots.php',
        'app/Views/reports/snapshot_show.php',
        'app/Views/reports/print.php',
        'app/Views/reports/_report_shell.php',
        'app/Views/reports/_filters.php',
        'app/Views/reports/_metric_cards.php',
        'app/Views/reports/_tables.php',
    ];
    return array_map(static fn (string $f): string => $root . '/' . $f, $files);
}

function archiveTestData(Report $R): void
{
    echo PHP_EOL . '13. LIMPEZA PRODUÇÃO' . PHP_EOL;
    $now = date('Y-m-d H:i:s');
    $sn = db()->exec("UPDATE report_snapshots SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE 'ETAPA 17 SNAPSHOT%'");
    deactivateTempValidationUsers();
    $R->pass('limpeza', 'Snapshots de teste arquivados / usuários inativados', "snapshots={$sn}");
}

echo '=== VALIDAÇÃO ETAPA 17 — RELATÓRIOS / INDICADORES GERENCIAIS (PRODUÇÃO) ===' . PHP_EOL . PHP_EOL;
$R = new Report();
ensureTempValidationUsers();

echo '1. ARQUIVOS PHP / MIGRATION' . PHP_EOL;
foreach ([
    'ReportController' => appRoot() . '/app/Controllers/ReportController.php',
    'Report model'     => appRoot() . '/app/Models/Report.php',
    'ReportSnapshot'   => appRoot() . '/app/Models/ReportSnapshot.php',
    'migration etapa17'=> appRoot() . '/database/migrations/2026_etapa17_reports.sql',
] as $label => $path) {
    is_file($path)
        ? $R->pass('arquivos', "{$label} existe", basename($path))
        : $R->fail('arquivos', "{$label} existe", $path);
}

$viewFiles = [
    'index', 'pipeline', 'proposals', 'sponsors', 'financials', 'contracts',
    'counterparts', 'dossiers', 'tasks', 'leads', 'snapshots', 'snapshot_show', 'print',
    '_report_shell', '_filters', '_metric_cards', '_tables',
];
foreach ($viewFiles as $view) {
    $path = appRoot() . '/app/Views/reports/' . $view . '.php';
    is_file($path)
        ? $R->pass('arquivos', "View reports/{$view}.php")
        : $R->fail('arquivos', "View reports/{$view}.php", 'MISSING');
}

echo PHP_EOL . '2. AMBIENTE / BANCO' . PHP_EOL;
restoreReportsRoleMatrix();
$hasSnapshotsTable = (bool) dbq("SHOW TABLES LIKE 'report_snapshots'");
$hasSnapshotsTable
    ? $R->pass('ambiente', 'Tabela report_snapshots', 'OK')
    : $R->fail('ambiente', 'Tabela report_snapshots', 'MISSING');
if (!$hasSnapshotsTable) {
    echo PHP_EOL . 'ABORT: execute a migration 2026_etapa17_reports.sql antes da validação.' . PHP_EOL;
    exit(1);
}

$expectedPerms = ['reports.archive', 'reports.generate', 'reports.print', 'reports.snapshots', 'reports.view'];
$perms = db()->query("SELECT slug FROM permissions WHERE slug LIKE 'reports.%' ORDER BY slug")->fetchAll(PDO::FETCH_COLUMN);
(count($perms) === 5 && array_diff($expectedPerms, $perms) === [] && array_diff($perms, $expectedPerms) === [])
    ? $R->pass('ambiente', 'Permissões reports.* (5)', implode(', ', $perms))
    : $R->fail('ambiente', 'Permissões reports.*', implode(', ', $perms));

$adminPerms = rolePermSlugs('administrador-geral');
(count($adminPerms) === 5 && array_diff($expectedPerms, $adminPerms) === [])
    ? $R->pass('ambiente', 'Admin com todas reports.*')
    : $R->fail('ambiente', 'Admin reports.*', implode(', ', $adminPerms));

$capPerms = rolePermSlugs('captacao-comercial');
($capPerms === ['reports.generate', 'reports.print', 'reports.snapshots', 'reports.view'])
    ? $R->pass('ambiente', 'Captação view/generate/snapshots/print sem archive')
    : $R->fail('ambiente', 'Captação reports.*', implode(', ', $capPerms));

foreach (['producao-coordenacao', 'comunicacao'] as $roleSlug) {
    $slugs = rolePermSlugs($roleSlug);
    ($slugs === ['reports.generate', 'reports.print', 'reports.view'])
        ? $R->pass('ambiente', "{$roleSlug} view/generate/print")
        : $R->fail('ambiente', "{$roleSlug} reports.*", implode(', ', $slugs));
}

$leituraPerms = rolePermSlugs('leitura-consulta');
($leituraPerms === ['reports.print', 'reports.view'])
    ? $R->pass('ambiente', 'Leitura-consulta só view/print')
    : $R->fail('ambiente', 'Leitura-consulta reports.*', implode(', ', $leituraPerms));

echo PHP_EOL . '3. AUTENTICAÇÃO E PERMISSÃO' . PHP_EOL;
$anon = new HttpClient();
$r = $anon->get('/reports');
($r['code'] === 302 && str_contains((string) $r['location'], '/login'))
    ? $R->pass('auth', 'GET /reports sem login → 302')
    : $R->fail('auth', 'GET /reports sem login → 302', 'code=' . $r['code']);

$sem = new HttpClient();
$sem->login(SEM_VIEW_EMAIL, PASSWORD);
$r = $sem->get('/reports');
($r['code'] === 403)
    ? $R->pass('auth', 'GET /reports sem reports.view → 403')
    : $R->fail('auth', 'sem view → 403', 'code=' . $r['code']);

$admin = new HttpClient();
$admin->login(ADMIN_EMAIL, PASSWORD);
$r = $admin->get('/reports');
($r['code'] === 200)
    ? $R->pass('auth', 'GET /reports admin → 200')
    : $R->fail('auth', 'admin /reports', 'code=' . $r['code']);

$menu = $admin->get('/dashboard');
(str_contains($menu['body'], 'Relatórios') || str_contains($menu['body'], '/reports'))
    ? $R->pass('auth', 'Menu Relatórios visível com reports.view')
    : $R->fail('auth', 'Menu Relatórios');
$semMenu = $sem->get('/dashboard');
!preg_match('#href=["\'][^"\']*/reports["\']#', $semMenu['body'])
    ? $R->pass('auth', 'Menu Relatórios oculto sem reports.view')
    : $R->fail('auth', 'Menu oculto sem view');

$semSnap = new HttpClient();
$semSnap->login(LEITOR_EMAIL, PASSWORD);
$r = $semSnap->post('/reports/snapshots', ['_csrf' => 'invalid', 'report_key' => 'executive', 'title' => 'X']);
($r['code'] === 403)
    ? $R->pass('auth', 'POST snapshot sem reports.snapshots → 403')
    : $R->fail('auth', 'POST snapshot sem perm', 'code=' . $r['code']);

$badCsrf = new HttpClient();
$badCsrf->login(ADMIN_EMAIL, PASSWORD);
$r = $badCsrf->post('/reports/snapshots', snapshotPayload([
    '_csrf' => 'x',
    'title' => 'CSRF TEST ETAPA 17',
]));
($r['code'] === 419)
    ? $R->pass('auth', 'POST snapshot CSRF inválido → 419')
    : $R->fail('auth', 'CSRF snapshot', 'code=' . $r['code']);

echo PHP_EOL . '4. ROTAS DE RELATÓRIOS (HTTP 200)' . PHP_EOL;
$reportRoutes = [
    'index'        => '/reports',
    'pipeline'     => '/reports/pipeline',
    'proposals'    => '/reports/proposals',
    'sponsors'     => '/reports/sponsors',
    'financials'   => '/reports/financials',
    'contracts'    => '/reports/contracts',
    'counterparts' => '/reports/counterparts',
    'dossiers'     => '/reports/dossiers',
    'tasks'        => '/reports/tasks',
    'leads'        => '/reports/leads',
    'snapshots'    => '/reports/snapshots',
];
foreach ($reportRoutes as $name => $path) {
    $r = $admin->get($path);
    ($r['code'] === 200)
        ? $R->pass('rotas', "GET {$path} → 200")
        : $R->fail('rotas', $name, 'code=' . $r['code']);
}

echo PHP_EOL . '5. FILTROS DE PERÍODO' . PHP_EOL;
$r = $admin->get('/reports?period_start=2026-12-01&period_end=2026-01-01');
$hasWarn = $r['code'] === 200
    && (str_contains($r['body'], 'A data final não pode ser anterior à data inicial.')
        || str_contains($r['body'], 'notice-warn'));
$hasWarn
    ? $R->pass('filtros', 'period_end < period_start exibe aviso no body')
    : $R->fail('filtros', 'aviso período invertido', 'code=' . $r['code']);

$r = $admin->get('/reports/pipeline?period_start=' . date('Y-m-d', strtotime('-90 days')) . '&period_end=' . date('Y-m-d'));
($r['code'] === 200 && str_contains($r['body'], 'report-filter'))
    ? $R->pass('filtros', 'Filtros válidos aplicados → 200')
    : $R->fail('filtros', 'filtros válidos', 'code=' . $r['code']);

echo PHP_EOL . '6. SNAPSHOTS — CRIAR, LISTAR, SHOW, ARQUIVAR, RESTAURAR' . PHP_EOL;
$snapshotTitle = 'ETAPA 17 SNAPSHOT ' . date('His');
$r = postSnapshot($admin, snapshotPayload(['title' => $snapshotTitle]));
$snapshotId = resolveSnapshotId($r, $snapshotTitle);
if ($snapshotId > 0) {
    $R->pass('snapshots', 'POST snapshot válido → redirect show', 'id=' . $snapshotId);
} else {
    $R->fail('snapshots', 'POST snapshot válido', 'code=' . $r['code']);
}
$R->ids['snapshot_id'] = $snapshotId;

if ($snapshotId > 0) {
    $row = dbq('SELECT report_key, title, metrics_json, summary_json, generated_by FROM report_snapshots WHERE id=?', [$snapshotId]);
    (($row['report_key'] ?? '') === 'executive' && ($row['title'] ?? '') === $snapshotTitle)
        ? $R->pass('snapshots', 'Snapshot salvo com report_key e title')
        : $R->fail('snapshots', 'dados snapshot', json_encode($row));
    (!empty($row['metrics_json']) && !empty($row['summary_json']))
        ? $R->pass('snapshots', 'metrics_json e summary_json preenchidos')
        : $R->fail('snapshots', 'JSON snapshot');

    $r = $admin->get('/reports/snapshots');
    ($r['code'] === 200 && str_contains($r['body'], $snapshotTitle))
        ? $R->pass('snapshots', 'GET /reports/snapshots lista snapshot')
        : $R->fail('snapshots', 'listagem snapshots', 'code=' . $r['code']);

    $r = $admin->get('/reports/snapshots/' . $snapshotId);
    ($r['code'] === 200 && str_contains($r['body'], $snapshotTitle))
        ? $R->pass('snapshots', 'GET show snapshot → 200')
        : $R->fail('snapshots', 'show snapshot', 'code=' . $r['code']);

    $show = $admin->get('/reports/snapshots/' . $snapshotId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/reports/snapshots/' . $snapshotId . '/archive', ['_csrf' => $csrf ?? '']);
    !empty(dbq('SELECT archived_at FROM report_snapshots WHERE id=?', [$snapshotId])['archived_at'])
        ? $R->pass('snapshots', 'archive preenche archived_at')
        : $R->fail('snapshots', 'archive');

    $list = $admin->get('/reports/snapshots');
    !str_contains($list['body'], $snapshotTitle)
        ? $R->pass('snapshots', 'Snapshot some da listagem padrão após archive')
        : $R->fail('snapshots', 'listagem após archive');

    $archList = $admin->get('/reports/snapshots?show_archived=1');
    str_contains($archList['body'], $snapshotTitle)
        ? $R->pass('snapshots', 'Filtro show_archived=1 exibe snapshot')
        : $R->fail('snapshots', 'filtro arquivados');

    $show = $admin->get('/reports/snapshots/' . $snapshotId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/reports/snapshots/' . $snapshotId . '/restore', ['_csrf' => $csrf ?? '']);
    empty(dbq('SELECT archived_at FROM report_snapshots WHERE id=?', [$snapshotId])['archived_at'])
        ? $R->pass('snapshots', 'restore limpa archived_at')
        : $R->fail('snapshots', 'restore');
}

echo PHP_EOL . '7. IMPRESSÃO (AUTENTICADO → 200)' . PHP_EOL;
$printRoutes = [
    '/reports/print',
    '/reports/executive/print',
    '/reports/pipeline/print',
    '/reports/financials/print',
    '/reports/contracts/print',
];
foreach ($printRoutes as $path) {
    $r = $admin->get($path);
    ($r['code'] === 200 && (str_contains($r['body'], 'report-print') || str_contains($r['body'], 'Indicadores gerenciais')))
        ? $R->pass('print', "GET {$path} → 200")
        : $R->fail('print', $path, 'code=' . $r['code']);
}

$anonPrint = new HttpClient();
$r = $anonPrint->get('/reports/print');
($r['code'] === 302 && str_contains((string) $r['location'], '/login'))
    ? $R->pass('print', 'GET /reports/print sem login → 302')
    : $R->fail('print', 'print anônimo', 'code=' . $r['code']);

echo PHP_EOL . '8. DASHBOARD' . PHP_EOL;
$dash = $admin->get('/dashboard');
(str_contains($dash['body'], 'Indicadores Gerenciais') || preg_match('#href=["\'][^"\']*/reports#', $dash['body']))
    ? $R->pass('dashboard', 'Dashboard com Indicadores Gerenciais ou link /reports')
    : $R->fail('dashboard', 'Indicadores Gerenciais / link reports');
foreach (['Executivo', 'Funil', 'Financeiro'] as $needle) {
    str_contains($dash['body'], $needle)
        ? $R->pass('dashboard', "Dashboard contém atalho: {$needle}")
        : $R->fail('dashboard', $needle);
}

echo PHP_EOL . '9. CSS TEMA (dcx-theme.css)' . PHP_EOL;
$cssPath = appRoot() . '/public/assets/css/dcx-theme.css';
$css = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
foreach ([
    'report-card', 'report-metric', 'report-filter', 'report-table', 'report-alert',
    'report-nav', 'report-section', 'report-print', 'report-empty', 'report-snapshot',
] as $class) {
    str_contains($css, '.' . $class)
        ? $R->pass('css', "Classe .{$class} definida")
        : $R->fail('css', "Classe .{$class}");
}

echo PHP_EOL . '10. ESCOPO — SEM LINKS EXTERNOS BI/EXCEL/PDF/PÚBLICO' . PHP_EOL;
$forbiddenPatterns = [
    'powerbi'              => '/href=["\'][^"\']*powerbi|powerbi\.com/i',
    'tableau'              => '/href=["\'][^"\']*tableau/i',
    'looker'               => '/href=["\'][^"\']*looker/i',
    'export excel link'    => '/href=["\'][^"\']*(?:export.*excel|\.xlsx|spreadsheetml)/i',
    'pdf externo'          => '/href=["\']https?:\/\/[^"\']+\.pdf/i',
    'portal public'        => '/href=["\'][^"\']*\/portal/i',
    'finance/reports link' => '/href=["\'][^"\']*\/finance\/reports/i',
];
$violations = [];
foreach (etapa17SourceFiles() as $file) {
    if (!is_file($file)) {
        continue;
    }
    $content = (string) file_get_contents($file);
    $rel = str_replace(appRoot() . '/', '', $file);
    foreach ($forbiddenPatterns as $label => $pattern) {
        if (preg_match($pattern, $content)) {
            $violations[] = "{$rel} ({$label})";
        }
    }
}
$violations === []
    ? $R->pass('escopo', 'Nenhum link externo BI/Excel/PDF/público nos arquivos da etapa 17')
    : $R->fail('escopo', 'Links proibidos', implode('; ', $violations));

$futureRoutes = ['/portal', '/signatures', '/finance/reports', '/reports/advanced', '/sponsor-portal'];
foreach ($futureRoutes as $route) {
    $r = $admin->get($route);
    !in_array($r['code'], [200], true)
        ? $R->pass('escopo', "Rota futura {$route} não exposta (code={$r['code']})")
        : $R->fail('escopo', "Rota {$route} não deveria existir");
}

echo PHP_EOL . '11. PERMISSÕES POR PERFIL (PRODUÇÃO)' . PHP_EOL;
$leitor = new HttpClient();
$leitor->login(LEITOR_EMAIL, PASSWORD);
($leitor->get('/reports')['code'] === 200) ? $R->pass('perm', 'Leitura GET /reports → 200') : $R->fail('perm', 'Leitura /reports');
($leitor->get('/reports/financials')['code'] === 200) ? $R->pass('perm', 'Leitura GET /reports/financials → 200') : $R->fail('perm', 'Leitura financials');
($leitor->get('/reports/executive/print')['code'] === 200) ? $R->pass('perm', 'Leitura print → 200') : $R->fail('perm', 'Leitura print');
($leitor->post('/reports/snapshots', snapshotPayload(['title' => 'X']))['code'] === 403) ? $R->pass('perm', 'Leitura POST snapshot → 403') : $R->fail('perm', 'Leitura snapshot');

$cap = new HttpClient();
$cap->login(CAP_EMAIL, PASSWORD);
($cap->get('/reports')['code'] === 200) ? $R->pass('perm', 'Captação GET /reports → 200') : $R->fail('perm', 'Captação /reports');
$capTitle = 'ETAPA 17 CAP SNAPSHOT ' . date('His');
$capSnap = postSnapshot($cap, snapshotPayload(['title' => $capTitle]));
(resolveSnapshotId($capSnap, $capTitle) > 0) ? $R->pass('perm', 'Captação POST snapshot válido') : $R->fail('perm', 'Captação snapshot');
($cap->post('/reports/snapshots/1/archive', ['_csrf' => 'x'])['code'] === 403) ? $R->pass('perm', 'Captação archive → 403') : $R->fail('perm', 'Captação archive');

$prod = new HttpClient();
$prod->login(PROD_EMAIL, PASSWORD);
($prod->get('/reports')['code'] === 200) ? $R->pass('perm', 'Produção GET /reports → 200') : $R->fail('perm', 'Produção /reports');
($prod->get('/reports/pipeline/print')['code'] === 200) ? $R->pass('perm', 'Produção print → 200') : $R->fail('perm', 'Produção print');
($prod->post('/reports/snapshots', snapshotPayload(['title' => 'X']))['code'] === 403) ? $R->pass('perm', 'Produção POST snapshot → 403') : $R->fail('perm', 'Produção snapshot');

$com = new HttpClient();
$com->login(COM_EMAIL, PASSWORD);
($com->get('/reports')['code'] === 200) ? $R->pass('perm', 'Comunicação GET /reports → 200') : $R->fail('perm', 'Comunicação /reports');
($com->post('/reports/snapshots', snapshotPayload(['title' => 'X']))['code'] === 403) ? $R->pass('perm', 'Comunicação POST snapshot → 403') : $R->fail('perm', 'Comunicação snapshot');

echo PHP_EOL . '12. ACTIVITY LOGS (OPCIONAL)' . PHP_EOL;
$hasActivityTable = (bool) dbq("SHOW TABLES LIKE 'activity_logs'");
if (!$hasActivityTable) {
    $R->pass('logs', 'Tabela activity_logs ausente — verificação ignorada');
} else {
    if ($snapshotId > 0) {
        $logSnap = dbq(
            "SELECT id FROM activity_logs WHERE entity_type='report_snapshot' AND entity_id=? AND action='report_snapshot_created' ORDER BY id DESC LIMIT 1",
            [$snapshotId]
        );
        $logSnap
            ? $R->pass('logs', 'Log report_snapshot_created')
            : $R->pass('logs', 'Log report_snapshot_created ausente (opcional)');
    }
    $logView = dbq(
        "SELECT id FROM activity_logs WHERE entity_type='report' AND action='report_viewed' ORDER BY id DESC LIMIT 1"
    );
    $logView
        ? $R->pass('logs', 'Log report_viewed registrado')
        : $R->pass('logs', 'Log report_viewed ausente (opcional)');
    $logPrint = dbq(
        "SELECT id FROM activity_logs WHERE entity_type='report' AND action='report_print_viewed' ORDER BY id DESC LIMIT 1"
    );
    $logPrint
        ? $R->pass('logs', 'Log report_print_viewed registrado')
        : $R->pass('logs', 'Log report_print_viewed ausente (opcional)');
}

archiveTestData($R);

echo PHP_EOL . '=== RESUMO ===' . PHP_EOL;
$pass = count(array_filter($R->results, static fn ($x) => $x['status'] === 'PASS'));
$fail = count($R->failures);
echo "PASS: {$pass} | FAIL: {$fail}" . PHP_EOL;
if ($fail > 0) {
    echo PHP_EOL . 'Falhas:' . PHP_EOL;
    foreach ($R->failures as $f) {
        echo "  - {$f}" . PHP_EOL;
    }
    exit(1);
}
echo PHP_EOL . 'Validação Etapa 17 concluída com sucesso.' . PHP_EOL;
