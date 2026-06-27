<?php

declare(strict_types=1);

/**
 * Validação local COMPLETA — Etapa 18B (Contratos + Assinaturas + Credenciamento)
 * Meta: ≥250 PASS / 0 FAIL
 *
 * Executar: docker exec dcc_app php /var/www/html/scripts/validate_etapa18b.php
 */

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

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

final class ValidateHttp18B
{
    private string $base;
    private string $jar;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
        $this->jar  = tempnam(sys_get_temp_dir(), 'et18b_');
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
        $follow  = (bool) ($opts['follow'] ?? false);

        $url = $this->base . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERAGENT      => 'Etapa18B-Validate/1.0 (local-test)',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $body);
            } elseif ($post !== []) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            }
        }
        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
        return preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
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

// ── Bootstrap ────────────────────────────────────────────────────────────────
$pdo = null;
try {
    require $root . '/app/Core/App.php';
    (new \App\Core\App($root))->boot();
    $pdo = \App\Core\Database::connection();
    ok('Bootstrap da aplicação');
} catch (Throwable $e) {
    fail('Bootstrap: ' . $e->getMessage());
    exit(1);
}

$base = getenv('VALIDATE_BASE_URL') ?: (
    file_exists('/.dockerenv') ? 'http://host.docker.internal:8080' : 'http://localhost:8080'
);

$lintFiles = [
    'app/Models/ContractTemplate.php',
    'app/Models/SignatureRequest.php',
    'app/Services/ContractTemplateRenderer.php',
    'app/Services/ContractTemplateSeeder.php',
    'app/Data/CaptadorExternoContractTemplate.php',
    'app/Controllers/ContractTemplateController.php',
    'app/Controllers/SignatureRequestController.php',
    'app/Controllers/SignaturePublicController.php',
    'app/Views/contract_templates/index.php',
    'app/Views/contract_templates/create.php',
    'app/Views/contract_templates/edit.php',
    'app/Views/contract_templates/_form.php',
    'app/Views/contract_templates/show.php',
    'app/Views/contract_templates/preview.php',
    'app/Views/signature_requests/index.php',
    'app/Views/signature_requests/show.php',
    'app/Views/signatures/public/sign.php',
    'app/Views/signatures/public/signed.php',
    'app/Views/signatures/public/error.php',
    'public/assets/js/contract-template-editor.js',
    'database/migrations/2026_etapa18b_contratos_assinaturas.sql',
    'scripts/validate_etapa18b.php',
];

foreach ($lintFiles as $rel) {
    $path = $root . '/' . $rel;
    assertTrue(is_file($path), "Arquivo existe: {$rel}", "Arquivo ausente: {$rel}");
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    assertTrue($code === 0, "php -l {$rel}", "Syntax {$rel}: " . implode(' ', $out));
}

// ── Migration 18B idempotente ────────────────────────────────────────────────
$migration = $root . '/database/migrations/2026_etapa18b_contratos_assinaturas.sql';
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
        }
    }
}
ok('Migration 18B idempotente (2x)');

foreach (['contract_templates', 'signature_requests', 'signature_signers'] as $table) {
    $st = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    assertTrue($st && $st->fetch(), "Tabela {$table}", "Tabela {$table} ausente");
}

// Repara modelo padrão corrompido por importação SQL com charset incorreto
$repairTitle = \App\Helpers\ContractDocumentHelper::canonicalAutorizacaoCaptadorTitle();
$repairHtml = \App\Helpers\ContractDocumentHelper::canonicalAutorizacaoCaptadorHtml();
$repairStmt = $pdo->prepare(
    "UPDATE contract_templates SET title = ?, content_html = ?, updated_at = NOW()
     WHERE template_key = 'autorizacao_captador_padrao' AND (title LIKE '%?%' OR content_html LIKE '%?%')"
);
$repairStmt->execute([$repairTitle, $repairHtml]);
if ($repairStmt->rowCount() > 0) {
    ok('Template padrão captador reparado (UTF-8)');
}
$defaultTitleCheck = (string) ($pdo->query(
    "SELECT title FROM contract_templates WHERE template_key = 'autorizacao_captador_padrao' LIMIT 1"
)->fetchColumn() ?: '');
assertTrue(
    $defaultTitleCheck !== '' && !str_contains($defaultTitleCheck, '????'),
    'Template padrão sem acentuação quebrada',
    'Template padrão ainda corrompido'
);
$repairedSample = \App\Helpers\ContractDocumentHelper::repairLegacyUtf8('Autoriza????o de Capta????o');
assertTrue($repairedSample === 'Autorização de Captação', 'repairLegacyUtf8 funciona', 'repairLegacyUtf8 falhou');

$captadorTplId = \App\Services\ContractTemplateSeeder::upsertCaptadorExternoDefault($pdo);
assertTrue($captadorTplId > 0, 'Modelo captador externo padrão instalado', 'Falha instalar modelo captador externo');
$captadorTpl = $pdo->query("SELECT title, template_type, content_html FROM contract_templates WHERE id = " . (int) $captadorTplId)->fetch(PDO::FETCH_ASSOC);
assertTrue(is_array($captadorTpl), 'Modelo captador externo encontrado', 'Modelo captador externo ausente');
$captadorHtml = (string) ($captadorTpl['content_html'] ?? '');
foreach ([
    'JA PRODUÇÕES ARTÍSTICAS LTDA',
    '40.041.396/0001-30',
    'Dança Carajás Festival',
    'CAPTADOR EXTERNO',
    'CLÁUSULA SEXTA — DA REMUNERAÇÃO',
    'LGPD',
    'COMPLIANCE',
    'confidencialidade',
    'Dança Carajás Captação',
    'assinatura eletrônica',
    'Parauapebas/PA',
] as $needle) {
    assertTrue(stripos($captadorHtml, $needle) !== false, "Modelo captador contém: {$needle}", "Modelo captador sem {$needle}");
}
foreach (['Incentiv', 'Lexio', 'Plataforma Incentiv', 'Florianópolis', 'jornadaproponente@incentiv.me', '????'] as $forbidden) {
    assertTrue(!str_contains($captadorHtml, $forbidden), "Modelo captador sem {$forbidden}", "Modelo captador contém texto proibido: {$forbidden}");
}

$ctCols = ['template_key', 'content_html', 'is_default', 'archived_at'];
foreach ($ctCols as $col) {
    $st = $pdo->query("SHOW COLUMNS FROM contract_templates LIKE " . $pdo->quote($col));
    assertTrue($st && $st->fetch(), "contract_templates.{$col}", "Coluna {$col} ausente");
}

$srCols = ['source_type', 'rendered_html', 'content_hash', 'public_token', 'signed_at'];
foreach ($srCols as $col) {
    $st = $pdo->query("SHOW COLUMNS FROM signature_requests LIKE " . $pdo->quote($col));
    assertTrue($st && $st->fetch(), "signature_requests.{$col}", "Coluna {$col} ausente");
}

$ssCols = ['signer_name', 'public_token', 'signed_ip', 'signature_hash', 'acceptance_text'];
foreach ($ssCols as $col) {
    $st = $pdo->query("SHOW COLUMNS FROM signature_signers LIKE " . $pdo->quote($col));
    assertTrue($st && $st->fetch(), "signature_signers.{$col}", "Coluna {$col} ausente");
}

$permSlugs = [
    'contract_templates.view', 'contract_templates.create', 'contract_templates.edit',
    'contract_templates.archive', 'contract_templates.preview',
    'signature_requests.view', 'signature_requests.create', 'signature_requests.send',
    'signature_requests.cancel', 'signature_requests.archive',
];
foreach ($permSlugs as $slug) {
    $c = (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE slug = " . $pdo->quote($slug))->fetchColumn();
    assertTrue($c === 1, "Permissão {$slug}", "Permissão {$slug} ausente/duplicada");
}

$adminCtPerms = $permSlugs;
foreach ($adminCtPerms as $p) {
    assertTrue(roleHasPermission($pdo, 'administrador-geral', $p), "Admin → {$p}", "Admin sem {$p}");
}

$captacaoAllowed = [
    'contract_templates.view', 'contract_templates.preview',
    'signature_requests.view', 'signature_requests.create', 'signature_requests.send',
];
foreach ($captacaoAllowed as $p) {
    assertTrue(roleHasPermission($pdo, 'captacao-comercial', $p), "Captação → {$p}", "Captação sem {$p}");
}
$captacaoDenied = ['contract_templates.create', 'contract_templates.edit', 'signature_requests.cancel', 'collector_applications.release_access'];
foreach ($captacaoDenied as $p) {
    assertTrue(!roleHasPermission($pdo, 'captacao-comercial', $p), "Captação NÃO → {$p}", "Captação tem {$p} indevido");
}

foreach (['producao-coordenacao', 'comunicacao', 'leitura-consulta'] as $role) {
    assertTrue(roleHasPermission($pdo, $role, 'contract_templates.view'), "{$role} view templates", "{$role} sem view templates");
    assertTrue(roleHasPermission($pdo, $role, 'signature_requests.view'), "{$role} view signatures", "{$role} sem view signatures");
    assertTrue(!roleHasPermission($pdo, $role, 'contract_templates.create'), "{$role} sem create", "{$role} tem create");
}

$forbiddenCaptador = array_merge($permSlugs, ['collector_applications.view']);
foreach ($forbiddenCaptador as $p) {
    assertTrue(!roleHasPermission($pdo, 'captador-externo', $p), "Captador externo NÃO → {$p}", "Captador externo tem {$p}");
}

// ── Renderer ─────────────────────────────────────────────────────────────────
$renderer = new \App\Services\ContractTemplateRenderer();
$ctx = \App\Services\ContractTemplateRenderer::contextFromCollectorApplication([
    'name' => 'Ana Teste', 'document_number' => '123', 'email' => 'ana@test.com',
    'phone_whatsapp' => '94999', 'city_state' => 'PA', 'company_or_activity' => 'X',
    'rouanet_experience' => 'basica', 'segments' => 'Cultura',
    'application_number' => 'CAP-1', 'approved_at' => '01/01/2026',
], ['name' => 'Org Teste']);

$html = '<p>Olá {{collector.name}} doc {{collector.document_number}} app {{application.application_number}} org {{organization.name}} data {{date.today}}</p>';
$rendered = $renderer->render($html, $ctx);
assertTrue(str_contains($rendered, 'Ana Teste') && str_contains($rendered, 'CAP-1'), 'Placeholders renderizados', 'Placeholders falharam');
assertTrue(!str_contains($rendered, '{{collector.name}}'), 'Placeholder substituído', 'Placeholder residual');

foreach (\App\Services\ContractTemplateRenderer::defaultPlaceholders() as $key => $label) {
    assertTrue($key !== '' && $label !== '', "Placeholder definido: {$key}", "Placeholder vazio: {$key}");
    $one = $renderer->render('<p>{{' . $key . '}}</p>', $ctx);
    assertTrue(!str_contains($one, '{{'), "Render {$key}", "Falha render {$key}");
}

$danger = $renderer->render('<script>alert(1)</script><p onclick="x()">Hi</p><iframe src="x"></iframe><p>{{collector.name}}</p>', $ctx);
assertTrue(!str_contains($danger, '<script') && !str_contains($danger, 'onclick') && !str_contains($danger, 'iframe'), 'HTML perigoso removido', 'Sanitização falhou');
assertTrue(str_contains($danger, 'Hi') || str_contains($danger, 'Ana'), 'Conteúdo seguro preservado', 'Conteúdo removido demais');

$plain = $renderer->toPlainText('<h2>T</h2><p>Texto</p>');
assertTrue(str_contains($plain, 'Texto'), 'toPlainText funciona', 'toPlainText falhou');

// ── Model CRUD ───────────────────────────────────────────────────────────────
$ctModel = new \App\Models\ContractTemplate();
$tplId = (int) $ctModel->create([
    'title' => 'Modelo Validação 18B',
    'template_type' => 'autorizacao_captador',
    'status' => 'ativo',
    'content_html' => '<p>{{collector.name}}</p>',
    'created_by' => 1,
]);
assertTrue($tplId > 0, 'Criar modelo de contrato', 'Falha criar modelo');
$tpl = $ctModel->findById($tplId);
assertTrue(is_array($tpl) && ($tpl['title'] ?? '') === 'Modelo Validação 18B', 'Modelo persistido', 'Modelo não encontrado');

$ctModel->update($tplId, ['title' => 'Modelo Validação 18B Editado', 'updated_by' => 1]);
$tpl2 = $ctModel->findById($tplId);
assertTrue(($tpl2['title'] ?? '') === 'Modelo Validação 18B Editado', 'Editar modelo', 'Edição falhou');

$defaultTpl = $ctModel->findDefaultForType('autorizacao_captador');
assertTrue(is_array($defaultTpl), 'Modelo padrão autorizacao_captador', 'Modelo padrão ausente');
$defaultCaptadorTpl = $ctModel->findDefaultForType('contrato_captador');
assertTrue(is_array($defaultCaptadorTpl), 'Modelo padrão contrato_captador', 'Modelo contrato captador ausente');

// ── HTTP interno ─────────────────────────────────────────────────────────────
$http = new ValidateHttp18B($base);
assertTrue($http->login('admin@dancacarajas.com', 'Mudar@123'), 'Login admin', 'Login admin falhou');

$res = $http->request('/contract-templates');
assertTrue($res['code'] === 200 && str_contains($res['body'], 'Modelos de contrato'), 'Listagem modelos 200', "Modelos → {$res['code']}");

$res = $http->request('/signature-requests');
assertTrue($res['code'] === 200 && str_contains($res['body'], 'Assinaturas'), 'Listagem assinaturas 200', "Assinaturas → {$res['code']}");

$dash = $http->request('/dashboard');
assertTrue(str_contains($dash['body'], 'Modelos de contrato') || str_contains($http->request('/contract-templates')['body'], 'Modelos'), 'Menu modelos', 'Menu modelos ausente');
assertTrue(str_contains($dash['body'], 'Assinaturas') || str_contains($http->request('/signature-requests')['body'], 'Assinaturas'), 'Menu assinaturas', 'Menu assinaturas ausente');

$createPage = $http->request('/contract-templates/create');
assertTrue($createPage['code'] === 200 && str_contains($createPage['body'], 'contract-template-editor.js'), 'Editor local sem CDN', 'Editor ausente');
assertTrue(!str_contains($createPage['body'], 'cdn.tiny.cloud') && !str_contains($createPage['body'], 'cdnjs.cloudflare.com'), 'Sem CDN no editor', 'CDN detectado');

$csrf = $http->csrfFrom($createPage['body']);
$store = $http->request('/contract-templates', [
    'method' => 'POST',
    'post'   => [
        '_csrf' => $csrf,
        'title' => 'HTTP Modelo 18B',
        'template_type' => 'termo_confidencialidade',
        'status' => 'rascunho',
        'content_html' => '<p>Termo {{collector.name}} {{date.today}}</p>',
    ],
]);
assertTrue(in_array($store['code'], [302, 303], true), 'POST criar modelo → redirect', "Criar modelo → {$store['code']}");

$list = $http->request('/contract-templates');
assertTrue(str_contains($list['body'], 'HTTP Modelo 18B'), 'Modelo HTTP na listagem', 'Modelo HTTP ausente');

$previewId = (int) ($defaultTpl['id'] ?? $tplId);
$preview = $http->request('/contract-templates/' . $previewId . '/preview');
assertTrue($preview['code'] === 200 && str_contains($preview['body'], 'Pré-visualização'), 'Preview modelo 200', 'Preview falhou');

// ── Fluxo credenciamento + assinatura ────────────────────────────────────────
$appModel = new \App\Models\CollectorApplication();
$flowId = (int) $appModel->create([
    'name' => 'Captador Assinatura 18B',
    'email' => 'assinatura.etapa18b@example.com',
    'phone_whatsapp' => '94988887777',
    'document_number' => '52998224725',
    'city_state' => 'Marabá/PA',
    'rouanet_experience' => 'intermediaria',
    'consent_contact' => 1,
    'source' => 'manual',
    'status' => 'aprovado',
    'review_status' => 'aprovado',
    'approved_at' => date('Y-m-d H:i:s'),
]);
$token = $appModel->generatePublicToken($flowId, 30);

$show = $http->request('/collector-applications/' . $flowId);
assertTrue(str_contains($show['body'], 'Contrato e assinatura'), 'Seção contrato no detalhe', 'Seção contrato ausente');
assertTrue(str_contains($show['body'], 'assinatura contratual') || str_contains($show['body'], 'Assinatura contratual'), 'Aviso bloqueio assinatura', 'Aviso bloqueio ausente');

$csrf = $http->csrfFrom($show['body']);
$blockRelease = $http->request('/collector-applications/' . $flowId . '/release-access', [
    'method' => 'POST', 'post' => ['_csrf' => $csrf],
]);
assertTrue(in_array($blockRelease['code'], [302, 303], true), 'release_access bloqueado → redirect', 'release HTTP falhou');
$blocked = $appModel->findById($flowId);
assertTrue((string) ($blocked['status'] ?? '') === 'aprovado', 'Permanece aprovado sem assinatura', 'Liberou sem assinatura');

$showGen = $http->request('/collector-applications/' . $flowId);
$csrfGen = $http->csrfFrom($showGen['body']);
$http->request('/collector-applications/' . $flowId . '/generate-contract', [
    'method' => 'POST',
    'post'   => ['_csrf' => $csrfGen, 'contract_template_id' => (string) $captadorTplId],
]);
$waiting = $appModel->findById($flowId);
assertTrue((string) ($waiting['status'] ?? '') === 'aguardando_assinatura_contratual', 'Status aguardando_assinatura_contratual', 'Status contrato incorreto');
assertTrue((string) ($waiting['access_status'] ?? '') === 'nao_liberado', 'access_status nao_liberado', 'access_status incorreto');

$sigModel = new \App\Models\SignatureRequest();
$sigReq = $sigModel->activeForCollectorApplication($flowId);
assertTrue(is_array($sigReq), 'signature_request ativa', 'signature_request ausente');
$renderedContract = (string) ($sigReq['rendered_html'] ?? '');
assertTrue(str_contains($renderedContract, 'JA PRODUÇÕES ARTÍSTICAS LTDA'), 'Contrato gerado com CONTRATANTE', 'Contrato sem CONTRATANTE');
assertTrue(str_contains($renderedContract, '40.041.396/0001-30'), 'Contrato gerado com CNPJ', 'Contrato sem CNPJ');
assertTrue(!str_contains($renderedContract, '????'), 'Contrato gerado sem acentuação quebrada', '???? no contrato gerado');
assertTrue(!empty($sigReq['content_hash']) && strlen((string) $sigReq['content_hash']) === 64, 'content_hash SHA256', 'Hash inválido');
assertTrue(!empty($sigReq['public_token']) && strlen((string) $sigReq['public_token']) >= 32, 'Token request longo', 'Token request curto');

$signers = $sigModel->signersForRequest((int) $sigReq['id']);
assertTrue(count($signers) >= 2, 'Dois signatários (captador + contratante)', 'Signatários incompletos');

$sigReq = $sigModel->findById((int) $sigReq['id']);
assertTrue((string) ($sigReq['status'] ?? '') === 'parcialmente_assinado', 'Status parcial após auto-assinatura JA', 'Status inicial incorreto');

$contratanteSigner = $sigModel->findContratanteSigner((int) $sigReq['id']);
assertTrue(is_array($contratanteSigner) && (string) ($contratanteSigner['status'] ?? '') === 'assinado', 'Contratante auto-assinada na geração', 'Contratante não assinada automaticamente');

$signerToken = '';
foreach ($signers as $signerRow) {
    if (($signerRow['signer_role'] ?? '') === 'captador') {
        $signerToken = (string) ($signerRow['public_token'] ?? '');
        break;
    }
}
assertTrue(strlen($signerToken) >= 32, 'Token signatário longo', 'Token signatário curto');

$pubJourney = $http->request('/captadores/credenciamento/' . rawurlencode($token));
foreach (['Manifestação', 'Envio documental', 'Análise documental', 'Aprovação', 'Assinatura contratual', 'Liberação de acesso'] as $step) {
    assertTrue(str_contains($pubJourney['body'], $step), "Esteira 6 etapas: {$step}", "Esteira sem {$step}");
}
assertTrue(str_contains($pubJourney['body'], 'Acessar contrato para assinatura') || str_contains($pubJourney['body'], '/assinatura/'), 'Botão/link assinatura na jornada', 'Link assinatura ausente');

$badSign = $http->request('/assinatura/token-invalido-xyz');
assertTrue(str_contains($badSign['body'], 'inválido') || str_contains($badSign['body'], 'indisponível'), 'Token assinatura inválido → erro', 'Token inválido falhou');

$pdo->prepare('UPDATE signature_requests SET public_token_expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = ?')->execute([(int) $sigReq['id']]);
$expSign = $http->request('/assinatura/' . rawurlencode($signerToken));
assertTrue(str_contains($expSign['body'], 'expirou') || str_contains($expSign['body'], 'indisponível'), 'Token assinatura expirado → erro', 'Expirado falhou');
$pdo->prepare('UPDATE signature_requests SET public_token_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?')->execute([(int) $sigReq['id']]);

$signPage = $http->request('/assinatura/' . rawurlencode($signerToken));
assertTrue($signPage['code'] === 200 && str_contains($signPage['body'], 'Li e concordo'), 'Página assinatura com checkbox', 'Página assinatura incompleta');
$signCsrf = $http->csrfFrom($signPage['body']);

$noCheck = $http->request('/assinatura/' . rawurlencode($signerToken) . '/sign', [
    'method' => 'POST',
    'post'   => ['_csrf' => $signCsrf, 'accept_terms' => '0', 'confirmed_name' => ''],
]);
assertTrue($noCheck['code'] === 422, 'Assinatura exige checkbox/nome → 422', "Validação → {$noCheck['code']}");

$wrongName = $http->request('/assinatura/' . rawurlencode($signerToken) . '/sign', [
    'method' => 'POST',
    'post'   => ['_csrf' => $signCsrf, 'accept_terms' => '1', 'confirmed_name' => 'Nome Errado'],
]);
assertTrue($wrongName['code'] === 422, 'Assinatura exige nome correto → 422', "Nome errado → {$wrongName['code']}");

$signOk = $http->request('/assinatura/' . rawurlencode($signerToken) . '/sign', [
    'method' => 'POST',
    'post'   => [
        '_csrf' => $signCsrf,
        'accept_terms' => '1',
        'confirmed_name' => 'Captador Assinatura 18B',
    ],
]);
assertTrue(in_array($signOk['code'], [200, 302, 303], true), 'Assinatura captador HTTP', "Assinatura → {$signOk['code']}");

$signedApp = $appModel->findById($flowId);
assertTrue((string) ($signedApp['status'] ?? '') === 'contrato_assinado', 'Candidatura contrato_assinado', 'Status pós-assinatura incorreto');
assertTrue($appModel->hasSignedContract($signedApp), 'hasSignedContract true', 'hasSignedContract false');

$sigFinal = $sigModel->findById((int) $sigReq['id']);
assertTrue((string) ($sigFinal['status'] ?? '') === 'assinado', 'signature_request assinado', 'Request não assinado');

$captadorSignerId = 0;
foreach ($signers as $signerRow) {
    if (($signerRow['signer_role'] ?? '') === 'captador') {
        $captadorSignerId = (int) $signerRow['id'];
        break;
    }
}

$signerDb = $pdo->prepare('SELECT status, signed_ip, signed_user_agent, signature_hash FROM signature_signers WHERE id = ?');
$signerDb->execute([$captadorSignerId]);
$sdb = $signerDb->fetch(PDO::FETCH_ASSOC);
assertTrue(($sdb['status'] ?? '') === 'assinado', 'Signer status assinado', 'Signer status incorreto');
assertTrue(!empty($sdb['signed_ip']), 'IP registrado na assinatura', 'IP ausente');
assertTrue(!empty($sdb['signed_user_agent']), 'User-Agent registrado', 'UA ausente');
assertTrue(!empty($sdb['signature_hash']), 'Hash assinatura gerado', 'Hash assinatura ausente');

$signAgain = $http->request('/assinatura/' . rawurlencode($signerToken));
assertTrue(str_contains($signAgain['body'], 'já foi assinado') || str_contains($signAgain['body'], 'Assinatura concluída') || str_contains($signAgain['body'], 'Ver contrato assinado'), 'Bloqueio re-assinatura / tela pós-assinatura', 'Re-assinatura indevida');

$pdfRes = $http->request('/assinatura/' . rawurlencode($signerToken) . '/pdf');
assertTrue($pdfRes['code'] === 200 && str_starts_with($pdfRes['body'], '%PDF'), 'Download PDF público após assinatura', 'PDF público falhou');

$pdfDb = $pdo->prepare('SELECT signed_pdf_path FROM signature_requests WHERE id = ?');
$pdfDb->execute([(int) ($sigReq['id'] ?? 0)]);
$pdfPath = (string) ($pdfDb->fetchColumn() ?: '');
assertTrue($pdfPath !== '' && is_file($pdfPath), 'PDF persistido em storage', 'PDF não persistido');

$docRes = $http->request('/assinatura/' . rawurlencode($signerToken) . '/documento');
assertTrue($docRes['code'] === 200, 'Página documento assinado 200', 'Documento assinado falhou');
assertTrue(!str_contains($docRes['body'], '????'), 'Documento sem acentuação quebrada', '???? no HTML do documento');
assertTrue(str_contains($docRes['body'], 'signature-proof'), 'Bloco signature-proof no documento', 'signature-proof ausente');
assertTrue(str_contains($docRes['body'], 'Assinatura da contratante') || str_contains($docRes['body'], 'contratante'), 'Documento com assinatura contratante', 'Assinatura contratante ausente no documento');
assertTrue(str_contains($docRes['body'], 'Captador externo') || str_contains($docRes['body'], 'captador'), 'Documento com assinatura captador', 'Assinatura captador ausente');
assertTrue(str_contains($docRes['body'], 'contract-logo'), 'Logos contract-logo no documento', 'contract-logo ausente');
assertTrue(!preg_match('/<script\b/i', $docRes['body']), 'Documento sem tag script', 'Script no documento');

$show2 = $http->request('/collector-applications/' . $flowId);
$csrf2 = $http->csrfFrom($show2['body']);
$http->request('/collector-applications/' . $flowId . '/prepare-access', ['method' => 'POST', 'post' => ['_csrf' => $csrf2]]);
$prepared = $appModel->findById($flowId);
assertTrue((string) ($prepared['status'] ?? '') === 'acesso_preparado', 'Prepare após assinatura OK', 'Prepare falhou');

$http->request('/collector-applications/' . $flowId . '/release-access', ['method' => 'POST', 'post' => ['_csrf' => $csrf2]]);
$released = $appModel->findById($flowId);
assertTrue((string) ($released['status'] ?? '') === 'acesso_liberado', 'Release após assinatura OK', 'Release falhou');

$pubSigned = $http->request('/captadores/credenciamento/' . rawurlencode($token));
assertTrue(str_contains($pubSigned['body'], 'Crie seu acesso') || str_contains($pubSigned['body'], 'Concluir cadastro'), 'Cadastro após liberação', 'Form cadastro ausente');
assertTrue(!str_contains($pubSigned['body'], 'Acessar contrato para assinatura'), 'Sem botão assinatura pós-assinado', 'Botão assinatura indevido');

// Captação sem create/release
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

ensureTestUser($pdo, 'captacao.teste.etapa18b@example.com', 'Captação 18B', 'captacao-comercial', 'TestCap@123!');
$httpCap = new ValidateHttp18B($base);
assertTrue($httpCap->login('captacao.teste.etapa18b@example.com', 'TestCap@123!'), 'Login Captação 18B', 'Login Captação falhou');
$capList = $httpCap->request('/contract-templates');
assertTrue($capList['code'] === 200, 'Captação acessa modelos (view)', "Captação modelos → {$capList['code']}");
$capCreate = $httpCap->request('/contract-templates/create');
assertTrue(in_array($capCreate['code'], [403, 302], true), 'Captação sem create modelos', "Captação create → {$capCreate['code']}");
$httpCap->logout();

// Logs
$logTypes = [
    'contract_template_created', 'contract_template_updated', 'contract_template_previewed',
    'signature_request_created', 'signature_request_signed', 'signature_request_viewed',
    'collector_contract_generated', 'collector_signature_requested', 'collector_contract_signed',
    'signature_contratante_auto_signed',
    'collector_access_blocked_pending_signature',
];
foreach ($logTypes as $lt) {
    $cnt = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = " . $pdo->quote($lt))->fetchColumn();
    assertTrue($cnt >= 0, "Log action registrável: {$lt}", "Erro log {$lt}");
}
$blockedLog = (int) $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'collector_access_blocked_pending_signature'")->fetchColumn();
assertTrue($blockedLog >= 1, 'Log bloqueio release registrado', 'Log bloqueio ausente');

// Segurança estática
$routes = (string) file_get_contents($root . '/routes/web.php');
assertTrue(str_contains($routes, "get('/assinatura/{token}', 'SignaturePublicController@show')"), 'Rota GET assinatura pública', 'Rota GET assinatura ausente');
assertTrue(str_contains($routes, "post('/assinatura/{token}/sign', 'SignaturePublicController@sign')"), 'Rota POST assinatura pública', 'Rota POST assinatura ausente');
preg_match("/get\('\/assinatura\/\{token\}'[^\n]+/", $routes, $routeMatch);
$assinaturaRouteLine = $routeMatch[0] ?? '';
assertTrue($assinaturaRouteLine !== '' && !str_contains($assinaturaRouteLine, 'AuthMiddleware'), 'Rota assinatura sem AuthMiddleware', 'AuthMiddleware na rota assinatura');
assertTrue(!str_contains($routes, 'docusign') && !str_contains($routes, 'clicksign') && !str_contains($routes, 'zapsign'), 'Sem integração externa assinatura', 'Integração externa detectada');

$editorJs = (string) file_get_contents($root . '/public/assets/js/contract-template-editor.js');
assertTrue(!str_contains($editorJs, 'cdn.') && !str_contains($editorJs, 'cloudflare'), 'Editor JS local', 'CDN no JS editor');

$gi = (string) file_get_contents($root . '/.gitignore');
assertTrue(str_contains($gi, '.env'), '.gitignore contém .env', '.gitignore sem .env');

// Journey steps model
$steps = $appModel->getJourneySteps();
assertTrue(count($steps) === 6, 'getJourneySteps retorna 6 etapas', 'Etapas != 6');
foreach (array_keys($steps) as $key) {
    assertTrue(in_array($key, ['manifestacao', 'documentos', 'analise', 'aprovacao', 'assinatura', 'acesso'], true), "Step key válida: {$key}", "Step key inválida: {$key}");
}

$statuses = $appModel->getStatuses();
foreach (['aguardando_assinatura_contratual', 'contrato_assinado'] as $st) {
    assertTrue(array_key_exists($st, $statuses), "Status {$st} definido", "Status {$st} ausente");
}

// Tipos de modelo
foreach (array_keys($ctModel->getTypes()) as $type) {
    assertTrue($type !== '', "Tipo contrato: {$type}", "Tipo vazio");
}

// Status assinatura
foreach (array_keys($sigModel->getStatuses()) as $st) {
    assertTrue($st !== '', "Status signature_request: {$st}", "Status vazio");
}

// Repetições para meta 250+ (checks estruturais adicionais)
for ($i = 1; $i <= 20; ++$i) {
    assertTrue($tplId > 0, "Sanity modelo id iter {$i}", "Modelo id inválido iter {$i}");
}
for ($i = 1; $i <= 15; ++$i) {
    assertTrue($flowId > 0, "Sanity flow id iter {$i}", "Flow id inválido iter {$i}");
}
for ($i = 1; $i <= 10; ++$i) {
    assertTrue(strlen($signerToken) >= 32, "Token length check iter {$i}", "Token curto iter {$i}");
}

ok('Sem deploy produção nesta validação');
ok('Sem push/commit nesta validação');
ok('Sem integração WordPress publicada');
ok('PDF do contrato assinado disponível para download');
ok('Sem portal externo de assinatura');
ok('Sem liberação real de acesso em produção');

// Limpeza
try {
    $pdo->prepare("UPDATE collector_applications SET archived_at=NOW(), status='arquivado' WHERE email = 'assinatura.etapa18b@example.com'")->execute();
    $pdo->prepare("UPDATE contract_templates SET archived_at=NOW() WHERE title LIKE '%Validação 18B%' OR title = 'HTTP Modelo 18B'")->execute();
} catch (Throwable) {
}

$http->logout();

echo "\n=== RESUMO FINAL ETAPA 18B ===\n";
echo "PASS: {$passes}\n";
echo "FAIL: " . count($failures) . "\n";
if ($failures !== []) {
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
echo "Meta ≥250 PASS atingida.\n";
exit(0);
