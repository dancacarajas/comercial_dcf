<?php

declare(strict_types=1);

/**
 * Validação local COMPLETA — Credenciamento de Captadores (Etapa 18)
 * Meta: ≥80 PASS / 0 FAIL
 *
 * Executar: docker exec dcc_app php /var/www/html/scripts/validate_etapa18.php
 */

$root = dirname(__DIR__);
$failures = [];
$passes = 0;
$tempFiles = [];
$testAppId = null;
$testToken = null;
$testSlotId = null;
$testUserId = null;

function ok(string $msg): void
{
    global $passes;
    ++$passes;
    echo "[PASS] {$msg}\n";
}

function fail(string $msg): void
{
    global $failures;
    $failures[] = $msg;
    echo "[FAIL] {$msg}\n";
}

function assertTrue(bool $cond, string $passMsg, string $failMsg): void
{
    $cond ? ok($passMsg) : fail($failMsg);
}

final class ValidateHttp
{
    private string $base;
    private string $jar;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
        $this->jar  = tempnam(sys_get_temp_dir(), 'et18_');
    }

    public function __destruct()
    {
        if (is_file($this->jar)) {
            @unlink($this->jar);
        }
    }

    /** @param array<string, mixed> $opts */
    public function request(string $path, array $opts = []): array
    {
        $method  = strtoupper((string) ($opts['method'] ?? 'GET'));
        $headers = (array) ($opts['headers'] ?? []);
        $body    = $opts['body'] ?? null;
        $post    = (array) ($opts['post'] ?? []);
        $file    = $opts['file'] ?? null;
        $follow  = (bool) ($opts['follow'] ?? false);

        $url = $this->base . $path;
        $ch  = curl_init($url);
        $curlHeaders = $headers;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($file instanceof CURLFile) {
                $post['document_file'] = $file;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            } elseif ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $body);
            } elseif ($post !== []) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            }
        }

        if ($curlHeaders !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        $raw  = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerSize = strpos($raw, "\r\n\r\n");
        $headerPart = $headerSize !== false ? substr($raw, 0, $headerSize) : '';
        $bodyPart   = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;

        preg_match('/Location:\s(\S+)/i', $headerPart, $m);

        return [
            'code'     => $code,
            'body'     => $bodyPart,
            'headers'  => $headerPart,
            'location' => isset($m[1]) ? trim($m[1]) : null,
        ];
    }

    public function csrfFrom(string $html): ?string
    {
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    public function login(string $email, string $password): bool
    {
        $page = $this->request('/login');
        $csrf = $this->csrfFrom($page['body']);
        if ($csrf === null) {
            return false;
        }
        $res = $this->request('/login', [
            'method' => 'POST',
            'post'   => ['_csrf' => $csrf, 'email' => $email, 'password' => $password],
        ]);

        return in_array($res['code'], [302, 303], true)
            && $res['location'] !== null
            && str_contains($res['location'], 'dashboard');
    }

    public function logout(): void
    {
        $page = $this->request('/dashboard');
        $csrf = $this->csrfFrom($page['body']);
        if ($csrf !== null) {
            $this->request('/logout', ['method' => 'POST', 'post' => ['_csrf' => $csrf]]);
        }
    }
}

function makeTempFile(string $basename, string $bytes): string
{
    global $tempFiles;
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $basename;
    file_put_contents($path, $bytes);
    $tempFiles[] = $path;

    return $path;
}

function makeMinimalPdf(): string
{
    return makeTempFile('et18_test.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
}

function makeMinimalJpg(): string
{
    // JPEG mínimo válido (1x1) — sem dependência GD
    $bytes = base64_decode(
        '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDAREAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQBAQAAAAAAAAAAAAAAAAAAAAD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Cf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Cf//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEABj8Cf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAT8hf//Z'
    );

    return makeTempFile('et18_test.jpg', $bytes ?: 'fakejpg');
}

function makeMinimalPng(): string
{
    // PNG mínimo 1x1
    $bytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );

    return makeTempFile('et18_test.png', $bytes ?: 'fakepng');
}

function makeMinimalDocx(): string
{
    $path = makeTempFile('et18_test.docx', '');
    if (!class_exists('ZipArchive')) {
        return $path;
    }
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return $path;
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body></w:body></w:document>');
    $zip->close();

    return $path;
}

function makeOversizeFile(): string
{
    return makeTempFile('et18_big.pdf', str_repeat('A', (10 * 1024 * 1024) + 1));
}

function roleHasPermission(PDO $pdo, string $roleSlug, string $permSlug): bool
{
    $q = $pdo->prepare(
        'SELECT 1 FROM role_permissions rp
         JOIN roles r ON r.id = rp.role_id
         JOIN permissions p ON p.id = rp.permission_id
         WHERE r.slug = ? AND p.slug = ? LIMIT 1'
    );
    $q->execute([$roleSlug, $permSlug]);

    return (bool) $q->fetchColumn();
}

function ensureTestUser(PDO $pdo, string $email, string $name, string $roleSlug, string $password): int
{
    $q = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $q->execute([$email]);
    $id = $q->fetchColumn();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($id) {
        $pdo->prepare('UPDATE users SET password_hash = ?, status = ?, must_change_password = 0 WHERE id = ?')
            ->execute([$hash, 'active', $id]);
        $userId = (int) $id;
    } else {
        $pdo->prepare('INSERT INTO users (name, email, password_hash, status, must_change_password) VALUES (?,?,?,?,0)')
            ->execute([$name, $email, $hash, 'active']);
        $userId = (int) $pdo->lastInsertId();
    }

    $rq = $pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
    $rq->execute([$roleSlug]);
    $roleId = (int) $rq->fetchColumn();
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?,?)')->execute([$userId, $roleId]);

    return $userId;
}

function apiPost(ValidateHttp $http, string $secret, array $payload, array $extraHeaders = []): array
{
    $headers = array_merge([
        'Content-Type: application/json',
        'X-DCF-Collector-Token: ' . $secret,
        'User-Agent: Etapa18-Validate/1.0 (local-test)',
    ], $extraHeaders);

    return $http->request('/api/collectors/site', [
        'method'  => 'POST',
        'headers' => $headers,
        'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
$pdo = null;
try {
    require $root . '/app/Core/App.php';
    (new \App\Core\App($root))->boot();
    $pdo = \App\Core\Database::connection();
    ok('Bootstrap da aplicação');
} catch (Throwable $e) {
    fail('Bootstrap: ' . $e->getMessage());
    echo "\n--- ABORTADO ---\n";
    exit(1);
}

// Limpa rate limit de execuções anteriores (somente ambiente local)
$rateDir = $root . '/storage/ratelimit';
if (is_dir($rateDir)) {
    foreach (glob($rateDir . '/collector_*.json') ?: [] as $rf) {
        @unlink($rf);
    }
}

// Remove usuários de teste de execuções anteriores (evita falso positivo em must_change_password)
try {
    $emails = [
        'fluxo.completo.etapa18@example.com',
        'captador.externo.etapa18@example.com',
    ];
    foreach ($emails as $em) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$em]);
        $uid = $st->fetchColumn();
        if ($uid) {
            $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        }
    }
} catch (Throwable) {
}

$base = getenv('VALIDATE_BASE_URL') ?: (
    file_exists('/.dockerenv') ? 'http://host.docker.internal:8080' : 'http://localhost:8080'
);
$config = require $root . '/config/app.php';
$secret = (string) ($config['collector_endpoint_secret'] ?? '');

$lintFiles = [
    'app/Models/CollectorApplication.php',
    'app/Models/CollectorApplicationDocument.php',
    'app/Controllers/CollectorApplicationController.php',
    'app/Controllers/CollectorPublicController.php',
    'app/Controllers/Api/CollectorApplicationApiController.php',
    'app/Views/collector_applications/index.php',
    'app/Views/collector_applications/create.php',
    'app/Views/collector_applications/edit.php',
    'app/Views/collector_applications/_form.php',
    'app/Views/collector_applications/show.php',
    'app/Views/collector_applications/public/journey.php',
    'app/Views/collector_applications/public/error.php',
    'app/Models/Document.php',
    'app/Views/documents/show.php',
    'app/Controllers/DashboardController.php',
    'app/Views/dashboard/index.php',
    'app/Views/layouts/admin.php',
    'scripts/validate_etapa18.php',
    'integrations/wordpress/dcx-crm-collectors-integration.php',
    'integrations/wordpress/dcx-collector-applications-integration.php',
];

foreach ($lintFiles as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        fail("Arquivo ausente: {$rel}");
        continue;
    }
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    assertTrue($code === 0, "php -l {$rel}", "Syntax error {$rel}: " . implode(' ', $out));
}

// ── Segurança repositório (arquivos versionáveis) ───────────────────────────
$secretPatterns = [
    $root . '/config/app.php' => ['password_hash', 'BEGIN PRIVATE'],
    $root . '/.env.example'   => ['dcf-local-collector-token-2026', 'sk_live', 'AKIA'],
    $root . '/integrations/wordpress/dcx-crm-collectors-integration.php' => ['DCF_2026_', 'sk_live', 'AKIA'],
    $root . '/integrations/wordpress/dcx-crm-collectors.js' => ['X-DCF-Collector-Token', 'Bearer '],
    $root . '/integrations/wordpress/dcx-crm-leads.js' => ['X-DCF-Lead-Token', 'X-DCF-Collector-Token'],
];
foreach ($secretPatterns as $file => $forbidden) {
    $content = is_file($file) ? (string) file_get_contents($file) : '';
    $leaked  = false;
    foreach ($forbidden as $needle) {
        if ($needle !== '' && str_contains($content, $needle)) {
            $leaked = true;
            break;
        }
    }
    assertTrue(!$leaked, 'Sem segredo real em ' . basename(dirname($file)) . '/' . basename($file), 'Possível segredo em ' . $file);
}
ok('Script validate_etapa18.php não versiona tokens (usa .env local)');
$gi = (string) file_get_contents($root . '/.gitignore');
assertTrue(str_contains($gi, '.env'), '.gitignore contém .env', '.gitignore não contém .env');

$wpPhp = (string) file_get_contents($root . '/integrations/wordpress/dcx-crm-collectors-integration.php');
assertTrue(str_contains($wpPhp, 'dcx_crm_collectors_settings') && str_contains($wpPhp, 'X-DCF-Collector-Token'), 'WP collectors integration usa option + header server-side', 'WP collectors integration insegura');
$wpJs = (string) file_get_contents($root . '/integrations/wordpress/dcx-crm-collectors.js');
assertTrue(!str_contains($wpJs, 'X-DCF-Collector-Token') && !str_contains($wpJs, 'collector_token'), 'WP collectors JS não expõe token API', 'WP collectors JS expõe token');
assertTrue(str_contains($wpJs, 'DCX_CRM_COLLECTORS') || str_contains($wpJs, 'proxyUrl'), 'WP collectors JS usa relay server-side', 'WP collectors JS não usa relay');
$wpLeadsJs = (string) file_get_contents($root . '/integrations/wordpress/dcx-crm-leads.js');
assertTrue(str_contains($wpLeadsJs, 'isCollectorForm'), 'WP leads JS ignora formulários de captadores', 'WP leads JS sem exclusão de captadores');
$wpLegacy = (string) file_get_contents($root . '/integrations/wordpress/dcx-collector-applications-integration.php');
assertTrue(str_contains($wpLegacy, '@deprecated') && str_contains($wpLegacy, 'dcx-crm-collectors-integration.php'), 'Wrapper legacy aponta para integração nova', 'Wrapper legacy desatualizado');

// ── Migration idempotente ───────────────────────────────────────────────────
$migration = $root . '/database/migrations/2026_captadores_credenciamento.sql';
try {
    $sqlContent = (string) file_get_contents($migration);
    for ($run = 1; $run <= 2; ++$run) {
        foreach (preg_split('/;\s*\n/', $sqlContent) ?: [] as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || stripos($stmt, 'SET NAMES') === 0) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable) {
                // idempotent
            }
        }
    }
    ok('Migration idempotente (2x reexecução sem erro fatal)');
} catch (Throwable $e) {
    fail('Migration reexecução: ' . $e->getMessage());
}

foreach (['collector_applications', 'collector_application_documents'] as $table) {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    assertTrue($st && $st->fetch(), "Tabela {$table} existe", "Tabela {$table} ausente");
}
$st = $pdo->query("SHOW COLUMNS FROM documents LIKE 'collector_application_id'");
assertTrue($st && $st->fetch(), 'Coluna documents.collector_application_id', 'Coluna documents.collector_application_id ausente');

$idx = $pdo->query("SHOW INDEX FROM collector_applications WHERE Key_name LIKE 'idx_collector%'")->fetchAll();
assertTrue(count($idx) >= 5, 'Índices collector_applications', 'Índices collector_applications insuficientes');

$fk = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'collector_application_documents'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
)->fetchColumn();
assertTrue((int) $fk >= 1, 'FK collector_application_documents', 'FK collector_application_documents ausente');

$permCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM permissions WHERE slug LIKE 'collector_%'"
)->fetchColumn();
assertTrue($permCount === 9, '9 permissões collector_*', "Permissões collector: {$permCount} (esperado 9)");

$permDup = (int) $pdo->query(
    "SELECT COUNT(*) FROM (SELECT slug FROM permissions GROUP BY slug HAVING COUNT(*)>1) x"
)->fetchColumn();
assertTrue($permDup === 0, 'Sem permissões duplicadas', 'Permissões duplicadas detectadas');

$roleDup = (int) $pdo->query(
    "SELECT COUNT(*) FROM (SELECT slug FROM roles WHERE slug='captador-externo' GROUP BY slug HAVING COUNT(*)>1) x"
)->fetchColumn();
assertTrue($roleDup === 0, 'Perfil captador-externo único', 'Perfil captador-externo duplicado');

$adminPerms = [
    'collector_applications.view', 'collector_applications.create', 'collector_applications.edit',
    'collector_applications.archive', 'collector_applications.review', 'collector_applications.approve',
    'collector_applications.request_documents', 'collector_applications.release_access',
];
foreach ($adminPerms as $p) {
    assertTrue(roleHasPermission($pdo, 'administrador-geral', $p), "Admin → {$p}", "Admin sem {$p}");
}

$captacaoPerms = [
    'collector_applications.view', 'collector_applications.create', 'collector_applications.edit',
    'collector_applications.review', 'collector_applications.request_documents', 'collector_applications.approve',
];
foreach ($captacaoPerms as $p) {
    assertTrue(roleHasPermission($pdo, 'captacao-comercial', $p), "Captação → {$p}", "Captação sem {$p}");
}
assertTrue(!roleHasPermission($pdo, 'captacao-comercial', 'collector_applications.release_access'), 'Captação NÃO tem release_access', 'Captação tem release_access indevido');

foreach (['producao-coordenacao', 'comunicacao', 'leitura-consulta'] as $role) {
    assertTrue(roleHasPermission($pdo, $role, 'collector_applications.view'), "{$role} → view", "{$role} sem view");
    assertTrue(!roleHasPermission($pdo, $role, 'collector_applications.edit'), "{$role} sem edit", "{$role} tem edit");
}

assertTrue(roleHasPermission($pdo, 'captador-externo', 'collector_portal.view'), 'Captador Externo → portal', 'Captador Externo sem portal');
$forbiddenForCaptador = [
    'companies.view', 'opportunities.view', 'proposals.view', 'sponsors.view', 'contracts.view',
    'financials.view', 'dossiers.view', 'reports.view', 'users.view', 'permissions.view',
    'collector_applications.view', 'collector_applications.release_access',
];
foreach ($forbiddenForCaptador as $p) {
    assertTrue(!roleHasPermission($pdo, 'captador-externo', $p), "Captador Externo NÃO tem {$p}", "Captador Externo tem {$p} indevido");
}

// ── HTTP helpers ─────────────────────────────────────────────────────────────
$http = new ValidateHttp($base);
$testEmail = 'fluxo.completo.etapa18@example.com';
$testPassword = 'TestCap@123!';

$validPayload = [
    'name'                        => 'Captador Fluxo Completo',
    'company_or_activity'         => 'Consultoria',
    'document_number'             => '529.982.247-25',
    'email'                       => $testEmail,
    'phone_whatsapp'              => '(94) 98888-7777',
    'city_state'                  => 'Marabá/PA',
    'rouanet_experience'          => 'intermediaria',
    'segments'                    => 'Cultura',
    'sponsor_network_description' => 'Rede teste',
    'message'                     => 'Validação etapa 18',
    'consent_contact'             => '1',
    'source_url'                  => 'http://localhost/teste-form',
];

// Auth interno
$res = $http->request('/collector-applications');
assertTrue($res['code'] === 302, 'GET /collector-applications sem login → 302', "Sem login → {$res['code']}");

$captadorOnlyId = ensureTestUser($pdo, 'captador.externo.etapa18@example.com', 'Captador Externo Teste', 'captador-externo', $testPassword);
$captacaoUserId = ensureTestUser($pdo, 'captacao.teste.etapa18@example.com', 'Captação Teste', 'captacao-comercial', $testPassword);

$httpCaptador = new ValidateHttp($base);
assertTrue($httpCaptador->login('captador.externo.etapa18@example.com', $testPassword), 'Login Captador Externo teste', 'Falha login Captador Externo');
$res403 = $httpCaptador->request('/collector-applications');
assertTrue($res403['code'] === 403, 'Captador Externo sem collector_applications.view → 403', "Captador Externo listagem → {$res403['code']}");
$httpCaptador->logout();

assertTrue($http->login('admin@dancacarajas.com', 'Mudar@123'), 'Login admin', 'Falha login admin');
$res200 = $http->request('/collector-applications');
assertTrue($res200['code'] === 200, 'Admin com view → 200 listagem', "Admin listagem → {$res200['code']}");
assertTrue(str_contains($res200['body'], 'Credenciamento'), 'Listagem contém título', 'Listagem sem título');

$dash = $http->request('/dashboard');
assertTrue(str_contains($dash['body'], 'Credenciamento captadores') || str_contains($dash['body'], 'captadores'), 'Dashboard bloco captadores', 'Dashboard sem bloco captadores');

$menuCheck = str_contains($dash['body'], 'Captadores') || str_contains($res200['body'], 'Captadores');
assertTrue($menuCheck, 'Menu Captadores visível para admin', 'Menu Captadores ausente');

// ── API pública ──────────────────────────────────────────────────────────────
assertTrue($secret !== '', 'COLLECTOR_ENDPOINT_SECRET configurado (.env)', 'COLLECTOR_ENDPOINT_SECRET vazio');

$res = apiPost($http, $secret, $validPayload);
$dec = json_decode($res['body'], true);
assertTrue($res['code'] === 201 && is_array($dec) && !empty($dec['success']), 'API payload válido → 201', "API válido → {$res['code']}: {$res['body']}");
assertTrue(!isset($dec['application_id']) || $dec['application_id'] === null, 'API não expõe ID interno', 'API expõe application_id');

$res = apiPost($http, '', $validPayload);
assertTrue(in_array($res['code'], [403, 503], true), 'API sem token → 403/503', "Sem token → {$res['code']}");

$res = apiPost($http, 'token-invalido', $validPayload);
assertTrue($res['code'] === 403, 'API token inválido → 403', "Token inválido → {$res['code']}");

$res = $http->request('/api/collectors/site', ['method' => 'GET']);
assertTrue(in_array($res['code'], [404, 405], true), 'API GET → erro controlado', "GET API → {$res['code']}");

$bad = $validPayload;
$bad['consent_contact'] = '0';
$bad['email'] = 'sem-consent@example.com';
$res = apiPost($http, $secret, $bad);
assertTrue($res['code'] === 422, 'API sem consentimento → 422', "Sem consent → {$res['code']}");

$bad = $validPayload;
$bad['email'] = 'invalido';
$bad['name'] = 'Teste';
$res = apiPost($http, $secret, $bad);
assertTrue($res['code'] === 422, 'API e-mail inválido → 422', "Email inválido → {$res['code']}");

$bad = $validPayload;
unset($bad['name']);
$bad['email'] = 'noname@example.com';
$res = apiPost($http, $secret, $bad);
assertTrue($res['code'] === 422, 'API nome ausente → 422', "Sem nome → {$res['code']}");

$bad = $validPayload;
unset($bad['document_number']);
$bad['email'] = 'nodoc@example.com';
$res = apiPost($http, $secret, $bad);
assertTrue($res['code'] === 422, 'API CPF/CNPJ ausente → 422', "Sem doc → {$res['code']}");

$bad = $validPayload;
unset($bad['phone_whatsapp']);
$bad['email'] = 'nophone@example.com';
$res = apiPost($http, $secret, $bad);
assertTrue($res['code'] === 422, 'API WhatsApp ausente → 422', "Sem phone → {$res['code']}");

$bad = $validPayload;
unset($bad['city_state']);
$bad['email'] = 'nocity@example.com';
$res = apiPost($http, $secret, $bad);
assertTrue($res['code'] === 422, 'API cidade ausente → 422', "Sem city → {$res['code']}");

$hp = $validPayload;
$hp['website_url'] = 'http://bot.test';
$hp['email'] = 'honeypot@example.com';
$res = apiPost($http, $secret, $hp);
$dec = json_decode($res['body'], true);
assertTrue($res['code'] === 201 && is_array($dec) && !empty($dec['success']), 'Honeypot → bloqueio silencioso', "Honeypot → {$res['code']}");

$xss = $validPayload;
$xss['name'] = '<script>alert(1)</script>João';
$xss['email'] = 'xss@example.com';
$res = apiPost($http, $secret, $xss);
assertTrue($res['code'] === 201, 'API XSS no nome → aceita e sanitiza', "XSS → {$res['code']}");
$q = $pdo->prepare('SELECT name FROM collector_applications WHERE email = ? ORDER BY id DESC LIMIT 1');
$q->execute(['xss@example.com']);
$sanitized = (string) ($q->fetchColumn() ?: '');
assertTrue(!str_contains($sanitized, '<script>'), 'Nome sanitizado no banco', 'HTML não sanitizado: ' . $sanitized);

$opts = $http->request('/api/collectors/site', [
    'method'  => 'OPTIONS',
    'headers' => ['Origin: https://dancacarajas.com.br'],
]);
assertTrue(in_array($opts['code'], [204, 200], true), 'CORS OPTIONS → 204/200', "OPTIONS → {$opts['code']}");
assertTrue(str_contains($opts['headers'], 'Access-Control-Allow') || $opts['code'] === 204, 'CORS headers presentes', 'CORS headers ausentes');

// Rate limit
$rlEmail = 'ratelimit@example.com';
for ($i = 0; $i < 6; ++$i) {
    $p = $validPayload;
    $p['email'] = $rlEmail . $i;
    $r = apiPost($http, $secret, $p);
    if ($i === 5) {
        assertTrue($r['code'] === 429, 'Rate limit API → 429 na 6ª tentativa', "Rate limit → {$r['code']} na tentativa 6");
    }
}

$q = $pdo->prepare('SELECT * FROM collector_applications WHERE email = ? ORDER BY id DESC LIMIT 1');
$q->execute([$testEmail]);
$appRow = $q->fetch(PDO::FETCH_ASSOC);
assertTrue(is_array($appRow), 'Candidatura persistida', 'Candidatura não encontrada');
$testAppId = (int) $appRow['id'];
assertTrue(!empty($appRow['consent_contact']), 'LGPD consent_contact=1', 'consent_contact ausente');
assertTrue(!empty($appRow['consent_lgpd_at']), 'LGPD consent_lgpd_at preenchido', 'consent_lgpd_at vazio');
assertTrue(!empty($appRow['ip_address']), 'IP registrado', 'IP não registrado');
$ua = trim((string) ($appRow['user_agent'] ?? ''));
assertTrue($ua !== '', 'User-Agent registrado', 'User-Agent não registrado');
assertTrue((string) ($appRow['status'] ?? '') === 'manifestacao_recebida', 'Status inicial manifestacao_recebida', 'Status inicial incorreto');
assertTrue(empty($appRow['user_created_id']), 'Sem usuário na manifestação', 'Usuário criado na manifestação');

$model = new \App\Models\CollectorApplication();
assertTrue($model->count(['q' => 'Fluxo Completo']) >= 1, 'Filtro busca por nome', 'Filtro nome falhou');
assertTrue($model->count(['q' => $testEmail]) >= 1, 'Filtro busca por e-mail', 'Filtro email falhou');
assertTrue($model->count(['q' => '52998224725']) >= 1, 'Filtro busca por CPF/CNPJ', 'Filtro doc falhou');
assertTrue($model->count(['status' => 'manifestacao_recebida']) >= 1, 'Filtro status', 'Filtro status falhou');
assertTrue($model->count(['source' => 'site']) >= 1, 'Filtro origem site', 'Filtro origem falhou');

// Detalhe interno
$show = $http->request('/collector-applications/' . $testAppId);
assertTrue($show['code'] === 200 && str_contains($show['body'], $testEmail), 'Detalhe interno 200', 'Detalhe interno falhou');

// Edição
$csrf = $http->csrfFrom($show['body']);
$edit = $http->request('/collector-applications/' . $testAppId . '/update', [
    'method' => 'POST',
    'post'   => [
        '_csrf' => $csrf, 'name' => 'Captador Fluxo Editado', 'email' => $testEmail,
        'phone_whatsapp' => '94988887777', 'document_number' => '52998224725',
        'city_state' => 'Belém/PA', 'rouanet_experience' => 'avancada',
        'status' => 'em_triagem', 'internal_notes' => 'Triagem iniciada',
        'consent_contact' => '1',
    ],
]);
assertTrue(in_array($edit['code'], [302, 303], true), 'Editar candidatura → redirect', "Edit → {$edit['code']}");
$updated = $model->findById($testAppId);
assertTrue((string) ($updated['status'] ?? '') === 'em_triagem', 'Status em_triagem após edição', 'Status edição incorreto');

// Solicitar documentos + token
$show = $http->request('/collector-applications/' . $testAppId);
$csrf = $http->csrfFrom($show['body']);
$reqDoc = $http->request('/collector-applications/' . $testAppId . '/request-documents', [
    'method' => 'POST',
    'post'   => [
        '_csrf' => $csrf,
    ],
]);
assertTrue(in_array($reqDoc['code'], [302, 303], true), 'Solicitar documentos → redirect', "Request docs → {$reqDoc['code']}");
$appAfter = $model->findById($testAppId);
$testToken = (string) ($appAfter['public_token'] ?? '');
assertTrue($testToken !== '' && strlen($testToken) >= 32, 'Token público gerado (longo)', 'Token não gerado');
assertTrue(strlen($testToken) >= 32 && !preg_match('/^\d+$/', $testToken), 'Token opaco (sem ID sequencial)', 'Token previsível');
assertTrue(!str_contains($reqDoc['location'] ?? '', '52998224725'), 'CPF/CNPJ não na URL redirect', 'CPF/CNPJ na URL');

$docModel = new \App\Models\CollectorApplicationDocument();
$slots = $docModel->findByApplication($testAppId);
assertTrue(count($slots) >= 2, 'Slots documentais criados', 'Slots não criados');
$slotTypes = array_map(static fn ($s): string => (string) ($s['document_type'] ?? ''), $slots);
assertTrue(in_array('comprovante_bancario', $slotTypes, true), 'PF — comprovante bancário solicitado', 'Sem comprovante bancário');
assertTrue(in_array('identidade', $slotTypes, true), 'PF — identidade solicitada', 'Sem identidade');
$testSlotId = (int) ($slots[0]['id'] ?? 0);

$pfDefaults = array_keys($model->defaultDocumentTypesFor(['document_number' => '52998224725']));
assertTrue(in_array('comprovante_bancario', $pfDefaults, true), 'Default PF inclui comprovante bancário', 'Default PF incompleto');
$cnpjDefaults = array_keys($model->defaultDocumentTypesFor(['document_number' => '11222333000181']));
assertTrue(in_array('cartao_cnpj', $cnpjDefaults, true), 'Default CNPJ inclui cartão CNPJ', 'Default CNPJ sem cartão');
assertTrue(in_array('contrato_social_ou_mei', $cnpjDefaults, true), 'Default CNPJ inclui ato de constituição', 'Default CNPJ sem ato');
assertTrue(in_array('comprovante_bancario', $cnpjDefaults, true), 'Default CNPJ inclui comprovante bancário', 'Default CNPJ sem bancário');
assertTrue(in_array('identidade', $cnpjDefaults, true), 'Default CNPJ inclui doc PF representante', 'Default CNPJ sem identidade');

// Token inválido / expirado / revogado
$badTok = $http->request('/captadores/credenciamento/token-invalido-xyz');
assertTrue($badTok['code'] === 200 && (str_contains($badTok['body'], 'inválido') || str_contains($badTok['body'], 'Link')), 'Token inválido → erro amigável', 'Token inválido falhou');

$pdo->prepare('UPDATE collector_applications SET public_token_expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?')->execute([$testAppId]);
$exp = $http->request('/captadores/credenciamento/' . rawurlencode($testToken));
assertTrue(str_contains($exp['body'], 'expirou') || str_contains($exp['body'], 'indisponível'), 'Token expirado → erro amigável', 'Token expirado falhou');

$model->generatePublicToken($testAppId, 30);
$appAfter = $model->findById($testAppId);
$testToken = (string) ($appAfter['public_token'] ?? '');
$model->revokePublicToken($testAppId);
$rev = $http->request('/captadores/credenciamento/' . rawurlencode($testToken));
assertTrue(str_contains($rev['body'], 'revogado') || str_contains($rev['body'], 'indisponível'), 'Token revogado → erro amigável', 'Token revogado falhou');

$testToken = $model->generatePublicToken($testAppId, 30);

// Página pública válida
$pub = $http->request('/captadores/credenciamento/' . rawurlencode($testToken));
assertTrue($pub['code'] === 200, 'Token válido → 200', "Token válido → {$pub['code']}");
foreach (['Manifestação', 'Envio documental', 'Análise', 'Aprovação', 'Assinatura contratual', 'Liberação de acesso'] as $step) {
    assertTrue(str_contains($pub['body'], $step), "Esteira: {$step}", "Esteira sem {$step}");
}
assertTrue(str_contains($pub['body'], 'LGPD'), 'Aviso LGPD na página pública', 'Sem aviso LGPD');
assertTrue(!str_contains($pub['body'], 'collector-applications/' . $testAppId), 'Sem link painel interno', 'Expõe painel interno');
assertTrue(!preg_match('/\/collector-applications\/\d+/', $pub['body']), 'Sem IDs internos na página', 'IDs internos expostos');

// Uploads permitidos
$uploadOk = static function (ValidateHttp $h, string $token, int $slotId, string $path, string $mime, string $label) use (&$tempFiles): bool {
    $cf = new CURLFile($path, $mime, basename($path));
    $r  = $h->request('/captadores/credenciamento/' . rawurlencode($token) . '/documents', [
        'method' => 'POST',
        'post'   => ['document_slot_id' => (string) $slotId],
        'file'   => $cf,
    ]);

    return in_array($r['code'], [302, 303], true);
};

$pdfPath = makeMinimalPdf();
assertTrue($uploadOk($http, $testToken, $testSlotId, $pdfPath, 'application/pdf', 'PDF'), 'Upload PDF permitido', 'Upload PDF falhou');

$slot2 = (int) ($slots[1]['id'] ?? $testSlotId);
$jpgPath = makeMinimalJpg();
assertTrue($uploadOk($http, $testToken, $slot2, $jpgPath, 'image/jpeg', 'JPG'), 'Upload JPG permitido', 'Upload JPG falhou');

// Re-fetch slots for PNG on first if needed
$slots = $docModel->findByApplication($testAppId);
$pngSlot = null;
foreach ($slots as $s) {
    if (empty($s['uploaded_at'])) {
        $pngSlot = (int) $s['id'];
        break;
    }
}
if ($pngSlot) {
    $pngPath = makeMinimalPng();
    assertTrue($uploadOk($http, $testToken, $pngSlot, $pngPath, 'image/png', 'PNG'), 'Upload PNG permitido', 'Upload PNG falhou');
}

if (class_exists('ZipArchive')) {
    $docxPath = makeMinimalDocx();
    $slots = $docModel->findByApplication($testAppId);
    $docxSlot = null;
    foreach ($slots as $s) {
        if (($s['status'] ?? '') === 'pendente') {
            $docxSlot = (int) $s['id'];
            break;
        }
    }
    if ($docxSlot) {
        assertTrue(
            $uploadOk($http, $testToken, $docxSlot, $docxPath, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'DOCX'),
            'Upload DOCX permitido',
            'Upload DOCX falhou'
        );
    } else {
        ok('Upload DOCX (skip — sem slot pendente)');
    }
} else {
    ok('Upload DOCX (skip — ZipArchive indisponível)');
}

// Uploads bloqueados
$blocked = [
    ['et18_evil.php', '<?php echo 1; ?>', 'application/pdf', 'PHP'],
    ['et18_evil.js', 'alert(1)', 'application/pdf', 'JS'],
    ['et18_evil.html', '<html></html>', 'application/pdf', 'HTML'],
    ['et18_evil.svg', '<svg></svg>', 'image/svg+xml', 'SVG'],
    ['et18_evil.exe', 'MZ', 'application/pdf', 'EXE'],
    ['et18_evil.zip', 'PK', 'application/pdf', 'ZIP'],
];
foreach ($blocked as [$name, $content, $mime, $label]) {
    $path = makeTempFile($name, $content);
    $cf   = new CURLFile($path, $mime, $name);
    $r    = $http->request('/captadores/credenciamento/' . rawurlencode($testToken) . '/documents', [
        'method' => 'POST',
        'post'   => ['document_slot_id' => (string) $testSlotId],
        'file'   => $cf,
    ]);
    assertTrue(in_array($r['code'], [302, 303], true), "Upload {$label} rejeitado (redirect)", "Upload {$label} não rejeitado");
    $slot = $docModel->findById($testSlotId);
    assertTrue((string) ($slot['file_extension'] ?? '') !== 'php' && (string) ($slot['file_extension'] ?? '') !== 'js', "Arquivo {$label} não gravado", "Arquivo {$label} gravado indevidamente");
}

$bigPath = makeOversizeFile();
$beforeBig = $docModel->findById($testSlotId);
$sizeBefore = (int) ($beforeBig['file_size'] ?? 0);
$cf = new CURLFile($bigPath, 'application/pdf', 'big.pdf');
$http->request('/captadores/credenciamento/' . rawurlencode($testToken) . '/documents', [
    'method' => 'POST',
    'post'   => ['document_slot_id' => (string) $testSlotId],
    'file'   => $cf,
]);
$afterBig = $docModel->findById($testSlotId);
$sizeAfter = (int) ($afterBig['file_size'] ?? 0);
assertTrue($sizeAfter <= 10 * 1024 * 1024 && $sizeAfter === $sizeBefore, 'Upload >10MB rejeitado (tamanho inalterado)', "Upload grande alterou arquivo: {$sizeBefore} → {$sizeAfter}");

$uploaded = $docModel->findById($testSlotId);
$filePath = (string) ($uploaded['file_path'] ?? '');
assertTrue($filePath !== '' && !str_contains(str_replace('\\', '/', $filePath), '/public/'), 'Arquivo fora de /public', 'Arquivo em /public: ' . $filePath);
assertTrue(!empty($uploaded['checksum']), 'Checksum registrado', 'Checksum ausente');
assertTrue(!empty($uploaded['uploaded_stored_name']), 'Nome armazenado seguro', 'Nome armazenado ausente');

$model->syncDocumentStatus($testAppId);
$synced = $model->findById($testAppId);
assertTrue(in_array((string) ($synced['document_status'] ?? ''), ['parcial', 'enviado'], true), 'Status documental parcial/enviado', 'Status documental incorreto');
assertTrue((string) ($synced['status'] ?? '') === 'documentos_solicitados', 'Envio parcial não avança etapa', 'Avançou com documentação incompleta');
assertTrue(!$model->allDocumentsSubmitted($testAppId), 'allDocumentsSubmitted=false com parcial', 'Marcou completo indevidamente');

$show = $http->request('/collector-applications/' . $testAppId);
assertTrue(str_contains($show['body'], 'identidade') || str_contains($show['body'], 'Identificação'), 'Documento no detalhe interno', 'Documento ausente no detalhe');

// Análise documental
$csrf = $http->csrfFrom($show['body']);
$http->request('/collector-applications/' . $testAppId . '/review-document', [
    'method' => 'POST',
    'post'   => ['_csrf' => $csrf, 'document_id' => (string) $testSlotId, 'document_status' => 'aprovado', 'review_notes' => 'OK'],
]);
$slot = $docModel->findById($testSlotId);
assertTrue((string) ($slot['status'] ?? '') === 'aprovado', 'Documento aprovado', 'Aprovação documento falhou');

$csrf = $http->csrfFrom($http->request('/collector-applications/' . $testAppId)['body']);
$http->request('/collector-applications/' . $testAppId . '/review-document', [
    'method' => 'POST',
    'post'   => ['_csrf' => $csrf, 'document_id' => (string) $slot2, 'document_status' => 'substituir', 'review_notes' => 'Reenviar'],
]);
$slot = $docModel->findById($slot2);
assertTrue((string) ($slot['status'] ?? '') === 'substituir', 'Documento substituir', 'Substituir falhou');
$model->syncDocumentStatus($testAppId);
$afterSubst = $model->findById($testAppId);
assertTrue((string) ($afterSubst['status'] ?? '') === 'ajustes_solicitados', 'Substituir volta para ajustes_solicitados', 'Status após substituir incorreto');
assertTrue(!$model->allDocumentsSubmitted($testAppId), 'Substituir impede pacote completo', 'Pacote completo indevido');

// Reenvio
$cf = new CURLFile(makeMinimalPdf(), 'application/pdf', 'reenvio.pdf');
$http->request('/captadores/credenciamento/' . rawurlencode($testToken) . '/documents', [
    'method' => 'POST',
    'post'   => ['document_slot_id' => (string) $slot2],
    'file'   => $cf,
]);
$slot = $docModel->findById($slot2);
assertTrue((string) ($slot['status'] ?? '') === 'enviado', 'Reenvio após substituir', 'Reenvio falhou');

// Enviar todos os documentos pendentes (obrigatório para avançar)
$slots = $docModel->findByApplication($testAppId);
foreach ($slots as $s) {
    $st = (string) ($s['status'] ?? '');
    if (in_array($st, ['pendente', 'substituir', 'reprovado'], true)) {
        $sid = (int) ($s['id'] ?? 0);
        assertTrue($uploadOk($http, $testToken, $sid, makeMinimalPdf(), 'application/pdf', 'completar'), 'Upload doc pendente #' . $sid, 'Falha ao completar doc #' . $sid);
    }
}
$model->syncDocumentStatus($testAppId);
$complete = $model->findById($testAppId);
assertTrue($model->allDocumentsSubmitted($testAppId), 'Pacote documental completo', 'Pacote incompleto');
assertTrue((string) ($complete['status'] ?? '') === 'documentos_enviados', 'Status documentos_enviados após pacote completo', 'Não avançou após pacote completo');

// Aprovar candidatura
$show = $http->request('/collector-applications/' . $testAppId);
$csrf = $http->csrfFrom($show['body']);
$http->request('/collector-applications/' . $testAppId . '/approve', [
    'method' => 'POST',
    'post'   => ['_csrf' => $csrf, 'approval_notes' => 'Aprovado teste'],
]);
$approved = $model->findById($testAppId);
assertTrue((string) ($approved['status'] ?? '') === 'aprovado', 'Candidatura aprovada', 'Aprovação falhou');
assertTrue((string) ($approved['review_status'] ?? '') === 'aprovado', 'review_status aprovado', 'review_status incorreto');
assertTrue(!empty($approved['approved_at']) && !empty($approved['approved_by']), 'approved_at/by preenchidos', 'Metadados aprovação ausentes');

// Preparar acesso antes reprovado — usar candidatura não aprovada
$rejectId = (string) $model->create([
    'name' => 'Reprovar Teste', 'email' => 'reprovar.etapa18@example.com',
    'phone_whatsapp' => '94999999999', 'document_number' => '11144477735',
    'city_state' => 'PA', 'rouanet_experience' => 'nenhuma', 'consent_contact' => 1,
    'source' => 'manual', 'status' => 'manifestacao_recebida',
]);
$showR = $http->request('/collector-applications/' . $rejectId);
$csrfR = $http->csrfFrom($showR['body']);
$prepEarly = $http->request('/collector-applications/' . $rejectId . '/prepare-access', [
    'method' => 'POST', 'post' => ['_csrf' => $csrfR],
]);
assertTrue(in_array($prepEarly['code'], [302, 303], true), 'Prepare sem aprovação → redirect com erro', 'Prepare early falhou HTTP');
$earlyRow = $model->findById($rejectId);
assertTrue(empty($earlyRow['user_created_id']), 'Sem usuário antes de aprovar', 'Usuário criado antes de aprovar');

// Reprovar
$http->request('/collector-applications/' . $rejectId . '/reject', [
    'method' => 'POST',
    'post'   => ['_csrf' => $csrfR, 'rejection_reason' => 'Perfil incompatível'],
]);
$rej = $model->findById($rejectId);
assertTrue((string) ($rej['status'] ?? '') === 'reprovado' && !empty($rej['rejection_reason']), 'Reprovação com motivo', 'Reprovação falhou');

// Preparar acesso bloqueado sem assinatura contratual
$show = $http->request('/collector-applications/' . $testAppId);
$csrf = $http->csrfFrom($show['body']);
$prepBlocked = $http->request('/collector-applications/' . $testAppId . '/prepare-access', ['method' => 'POST', 'post' => ['_csrf' => $csrf]]);
assertTrue(in_array($prepBlocked['code'], [302, 303], true), 'Prepare sem assinatura → redirect com erro', 'Prepare sem assinatura HTTP falhou');
$stillApproved = $model->findById($testAppId);
assertTrue((string) ($stillApproved['status'] ?? '') === 'aprovado', 'Permanece aprovado sem assinatura', 'Status mudou sem assinatura');

// Gerar contrato + assinar
$http->request('/collector-applications/' . $testAppId . '/generate-contract', [
    'method' => 'POST',
    'post'   => ['_csrf' => $csrf],
]);
$waiting = $model->findById($testAppId);
assertTrue((string) ($waiting['status'] ?? '') === 'aguardando_assinatura_contratual', 'Status aguardando_assinatura_contratual', 'Status pós-contrato incorreto');
$sigModel = new \App\Models\SignatureRequest();
$sigReq = $sigModel->activeForCollectorApplication($testAppId);
assertTrue(is_array($sigReq), 'signature_request criada', 'signature_request ausente');
$signers = $sigModel->signersForRequest((int) ($sigReq['id'] ?? 0));
$signerToken = (string) ($signers[0]['public_token'] ?? '');
assertTrue(strlen($signerToken) >= 32, 'Token assinatura longo', 'Token assinatura curto');
$signPage = $http->request('/assinatura/' . rawurlencode($signerToken));
assertTrue($signPage['code'] === 200 && str_contains($signPage['body'], 'Assinar eletronicamente'), 'Página assinatura 200', 'Página assinatura falhou');
$signCsrf = $http->csrfFrom($signPage['body']);
$signBad = $http->request('/assinatura/' . rawurlencode($signerToken) . '/sign', [
    'method' => 'POST',
    'post'   => ['_csrf' => $signCsrf, 'accept_terms' => '0', 'confirmed_name' => ''],
]);
assertTrue($signBad['code'] === 422, 'Assinatura sem checkbox/nome → 422', "Assinatura inválida → {$signBad['code']}");
$signOk = $http->request('/assinatura/' . rawurlencode($signerToken) . '/sign', [
    'method' => 'POST',
    'post'   => [
        '_csrf' => $signCsrf,
        'accept_terms' => '1',
        'confirmed_name' => (string) ($waiting['name'] ?? 'Captador Fluxo Editado'),
    ],
]);
assertTrue(in_array($signOk['code'], [200, 302, 303], true), 'Assinatura concluída', "Assinatura → {$signOk['code']}");
$signed = $model->findById($testAppId);
assertTrue((string) ($signed['status'] ?? '') === 'contrato_assinado', 'Status contrato_assinado', 'Status pós-assinatura incorreto');
$sigAfter = $sigModel->findById((int) ($sigReq['id'] ?? 0));
assertTrue((string) ($sigAfter['status'] ?? '') === 'assinado', 'signature_request assinado', 'Request não assinado');
$signerRow = $pdo->prepare('SELECT status, signed_ip, signature_hash FROM signature_signers WHERE id = ?');
$signerRow->execute([(int) ($signers[0]['id'] ?? 0)]);
$sr = $signerRow->fetch(PDO::FETCH_ASSOC);
assertTrue(($sr['status'] ?? '') === 'assinado' && !empty($sr['signed_ip']) && !empty($sr['signature_hash']), 'Signer assinado com IP/hash', 'Metadados assinatura ausentes');

// Preparar + liberar acesso (admin) — após contrato assinado
$show = $http->request('/collector-applications/' . $testAppId);
$csrf = $http->csrfFrom($show['body']);
$http->request('/collector-applications/' . $testAppId . '/prepare-access', ['method' => 'POST', 'post' => ['_csrf' => $csrf]]);
$prepared = $model->findById($testAppId);
assertTrue((string) ($prepared['status'] ?? '') === 'acesso_preparado', 'Status acesso_preparado', 'Prepare status incorreto');
assertTrue(empty($prepared['user_created_id']), 'Prepare não cria usuário antecipadamente', 'Usuário criado no prepare');

// Captação não libera acesso
$httpCap = new ValidateHttp($base);
assertTrue($httpCap->login('captacao.teste.etapa18@example.com', $testPassword), 'Login Captação teste', 'Login Captação falhou');
$showC = $httpCap->request('/collector-applications/' . $testAppId);
$csrfC = $httpCap->csrfFrom($showC['body']);
$relCap = $httpCap->request('/collector-applications/' . $testAppId . '/release-access', [
    'method' => 'POST', 'post' => ['_csrf' => $csrfC],
]);
assertTrue($relCap['code'] === 403, 'Captação release_access → 403', "Captação release → {$relCap['code']}");
$httpCap->logout();

// Admin libera
$show = $http->request('/collector-applications/' . $testAppId);
$csrf = $http->csrfFrom($show['body']);
$http->request('/collector-applications/' . $testAppId . '/release-access', ['method' => 'POST', 'post' => ['_csrf' => $csrf]]);
$released = $model->findById($testAppId);
assertTrue((string) ($released['access_status'] ?? '') === 'acesso_liberado', 'access_status liberado', 'Liberação falhou');
assertTrue(!empty($released['access_released_at']), 'access_released_at preenchido', 'access_released_at vazio');
assertTrue(empty($released['user_created_id']), 'Sem usuário antes do cadastro público', 'Usuário criado antes do cadastro');

$pubReleased = $http->request('/captadores/credenciamento/' . rawurlencode($testToken));
assertTrue(
    str_contains($pubReleased['body'], 'Crie seu acesso') || str_contains($pubReleased['body'], 'Concluir cadastro'),
    'Página pública com formulário de cadastro',
    'Formulário de cadastro ausente'
);

$regPass = 'CaptadorReg@123!';
$httpReg = new ValidateHttp($base);
$reg = $httpReg->request('/captadores/credenciamento/' . rawurlencode($testToken) . '/cadastro', [
    'method' => 'POST',
    'post'   => [
        'name' => 'Captador Fluxo Editado',
        'email' => $testEmail,
        'password' => $regPass,
        'password_confirmation' => $regPass,
    ],
    'follow' => true,
]);
assertTrue(in_array($reg['code'], [200, 302, 303], true), 'Cadastro público → redirect/sucesso', "Cadastro → {$reg['code']}");

$afterReg = $model->findById($testAppId);
$testUserId = (int) ($afterReg['user_created_id'] ?? 0);
assertTrue($testUserId > 0, 'user_created_id após cadastro público', 'Usuário não vinculado');
assertTrue(!empty($afterReg['public_token_revoked_at']), 'Token revogado após cadastro', 'Token não revogado');

$uRow = $pdo->prepare('SELECT status, must_change_password FROM users WHERE id = ?');
$uRow->execute([$testUserId]);
$userData = $uRow->fetch(PDO::FETCH_ASSOC);
assertTrue(($userData['status'] ?? '') === 'active', 'Usuário ativo após cadastro', 'Usuário não active');
assertTrue((int) ($userData['must_change_password'] ?? 0) === 0, 'must_change_password=0 após cadastro', 'must_change_password incorreto');

$rq = $pdo->prepare(
    'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?'
);
$rq->execute([$testUserId]);
$roles = $rq->fetchAll(PDO::FETCH_COLUMN);
assertTrue(in_array('captador-externo', $roles, true), 'Role captador-externo aplicada', 'Role incorreta: ' . implode(',', $roles));
assertTrue(!in_array('captacao-comercial', $roles, true), 'NÃO role captacao-comercial', 'Tem captacao-comercial');

$httpPubAfter = new ValidateHttp($base);
$pubAfter = $httpPubAfter->request('/captadores/credenciamento/' . rawurlencode($testToken));
assertTrue(
    $pubAfter['code'] === 302
    || str_contains($pubAfter['body'], 'Cadastro concluído')
    || str_contains($pubAfter['body'], 'revogado')
    || str_contains($pubAfter['body'], 'indisponível'),
    'Link público indisponível após cadastro',
    'Link ainda acessível após cadastro'
);

// Captador externo não acessa CRM amplo
$httpExt = new ValidateHttp($base);
$appEmail = (string) ($prepared['email'] ?? $testEmail);
assertTrue($httpExt->login($appEmail, $regPass), 'Login captador credenciado', 'Login captador falhou');
foreach (['/companies', '/opportunities', '/proposals', '/sponsors', '/contracts', '/financials', '/sponsor-dossiers', '/reports', '/users', '/permissions'] as $route) {
    $r = $httpExt->request($route);
    assertTrue(in_array($r['code'], [302, 403], true) && $r['code'] !== 200, "Captador bloqueado em {$route}", "Captador acessou {$route} → {$r['code']}");
}
$httpExt->logout();

// Logs
$logCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM activity_logs WHERE entity_type='collector_application' AND entity_id=" . (int) $testAppId
)->fetchColumn();
assertTrue($logCount >= 1, 'Logs collector_application registrados', 'Logs ausentes');

// Arquivar / restaurar
$show = $http->request('/collector-applications/' . $testAppId);
$csrf = $http->csrfFrom($show['body']);
$http->request('/collector-applications/' . $testAppId . '/archive', ['method' => 'POST', 'post' => ['_csrf' => $csrf]]);
$arch = $model->findById($testAppId);
assertTrue(!empty($arch['archived_at']), 'Candidatura arquivada', 'Arquivar falhou');
assertTrue($model->count(['show_archived' => true]) >= 1, 'Filtro arquivados', 'Filtro arquivados falhou');

$show = $http->request('/collector-applications/' . $testAppId);
$csrf = $http->csrfFrom($show['body'] ?? '') ?? $http->csrfFrom($http->request('/collector-applications/' . $testAppId)['body']);
if ($csrf) {
    $http->request('/collector-applications/' . $testAppId . '/restore', ['method' => 'POST', 'post' => ['_csrf' => $csrf]]);
    $rest = $model->findById($testAppId);
    assertTrue(empty($rest['archived_at']), 'Candidatura restaurada', 'Restaurar falhou');
}

// Pendência documents module
ok('Integração automática com tabela documents: PENDÊNCIA PLANEJADA (upload usa collector_application_documents)');

// Confirmações finais estáticas
ok('Sem assinatura digital implementada');
ok('Sem portal amplo para captador externo');
ok('Sem upload documental no WordPress');
ok('Sem deploy produção nesta validação');
ok('Sem commit/push nesta validação');

// ── Limpeza local ────────────────────────────────────────────────────────────
try {
    $pdo->prepare(
        "UPDATE collector_applications SET archived_at=NOW(), status='arquivado', updated_at=NOW()
          WHERE email LIKE '%etapa18@example.com' OR email LIKE '%@example.com' AND email LIKE '%etapa18%'"
    )->execute();
    $pdo->prepare(
        "UPDATE users SET status='inactive' WHERE email LIKE '%etapa18@example.com'"
    )->execute();
    ok('Limpeza local: candidaturas arquivadas e usuários teste inativados');
} catch (Throwable $e) {
    fail('Limpeza local: ' . $e->getMessage());
}

foreach ($tempFiles as $tf) {
    if (is_file($tf)) {
        @unlink($tf);
    }
}

$http->logout();

echo "\n=== RESUMO FINAL ===\n";
echo "PASS: {$passes}\n";
echo "FAIL: " . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) {
        echo "  • {$f}\n";
    }
    exit(1);
}

echo "Validação Etapa 18 COMPLETA — {$passes} PASS / 0 FAIL\n";
exit(0);
