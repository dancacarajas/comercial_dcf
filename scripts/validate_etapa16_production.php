<?php

declare(strict_types=1);

/**
 * Validação HTTP — Etapa 16 Dossiê do Patrocinador (produção)
 * Executar no servidor: php scripts/validate_etapa16_production.php
 */

const BASE_URL = 'https://comercial.dancacarajas.com.br';
const PASSWORD = 'Mudar@123';
const PROD_PREFIX = 'DOSSIÊ ETAPA 16 PRODUCAO';
const LEITOR_EMAIL = 'validacao-etapa16-leitor@test.com';
const CAP_EMAIL = 'validacao-etapa16-cap@test.com';
const PROD_EMAIL = 'validacao-etapa16-prod@test.com';

final class HttpClient
{
    private string $cookieFile;

    public function __construct()
    {
        $this->cookieFile = sys_get_temp_dir() . '/dcc_etapa16_' . bin2hex(random_bytes(4)) . '.txt';
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

function ensureSemDossiersUser(): void
{
    $row = dbq('SELECT id FROM users WHERE email = ?', ['sem-dossiers@test.com']);
    if (!$row) {
        $hash = dbq('SELECT password_hash FROM users WHERE id = 1')['password_hash'];
        db()->prepare(
            'INSERT INTO users (name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, NOW())'
        )->execute(['Sem Dossiês', 'sem-dossiers@test.com', $hash, 'active']);
        $uid = (int) db()->lastInsertId();
        $roleId = dbq("SELECT id FROM roles WHERE slug = 'leitura-consulta'")['id'] ?? null;
        if ($roleId) {
            db()->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$uid, $roleId]);
        }
    } else {
        $uid = (int) $row['id'];
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['active', $uid]);
    }
    db()->prepare(
        'DELETE rp FROM role_permissions rp
         INNER JOIN permissions p ON p.id = rp.permission_id
         INNER JOIN user_roles ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = ? AND p.slug LIKE ?'
    )->execute([$uid, 'dossiers.%']);
}

function dossierPayload(array $overrides = []): array
{
    return array_merge([
        'sponsor_id' => '', 'company_id' => '', 'contact_id' => '', 'opportunity_id' => '',
        'proposal_id' => '', 'quota_id' => '', 'main_contract_id' => '',
        'main_document_id' => '', 'final_document_id' => '', 'delivery_receipt_document_id' => '',
        'dossier_number' => '', 'title' => '',
        'dossier_type' => 'prestacao_comercial', 'status' => 'rascunho', 'delivery_status' => 'nao_entregue',
        'period_start' => '', 'period_end' => '',
        'include_contracts' => '1', 'include_counterparts' => '1', 'include_financials' => '1',
        'include_documents' => '1', 'include_evidence' => '1', 'include_clipping' => '1', 'include_media' => '1',
        'executive_summary' => '', 'commercial_summary' => '', 'counterparts_summary' => '',
        'financial_summary' => '', 'documents_summary' => '', 'pending_notes' => '',
        'approval_notes' => '', 'delivery_notes' => '', 'notes' => '', 'internal_notes' => '',
        'responsible_user_id' => '1',
    ], $overrides);
}

function postDossier(HttpClient $http, array $fields): array
{
    $page = $http->get('/sponsor-dossiers/create');
    $fields['_csrf'] = HttpClient::extractCsrf($page['body']) ?? '';
    return $http->post('/sponsor-dossiers', $fields);
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
            '_csrf' => $csrf, 'name' => 'EMPRESA TESTE — ETAPA 16', 'status' => 'prospect', 'priority' => 'B',
        ]);
        if (preg_match('#/companies/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
            $companyId = (int) $m[1];
        } else {
            $companyId = (int) (dbq("SELECT id FROM companies WHERE name LIKE '%ETAPA 16%' ORDER BY id DESC LIMIT 1")['id'] ?? 0);
        }
    }

    $page = $http->get('/sponsors/create?company_id=' . $companyId);
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/sponsors', [
        '_csrf' => $csrf,
        'company_id' => (string) $companyId,
        'sponsor_display_name' => 'PATROCINADOR BASE ETAPA 16',
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
    $prefix = 'ETAPA 16 DOSSIÊ';
    $page = $http->get('/companies/create');
    $csrf = HttpClient::extractCsrf($page['body']);
    $res = $http->post('/companies', [
        '_csrf' => $csrf, 'name' => $prefix . ' EMPRESA', 'status' => 'prospect', 'priority' => 'B',
    ]);
    $companyId = preg_match('#/companies/(\d+)#', (string) ($res['location'] ?? ''), $m) ? (int) $m[1]
        : (int) (dbq("SELECT id FROM companies WHERE name LIKE ? ORDER BY id DESC LIMIT 1", ['%' . $prefix . ' EMPRESA%'])['id'] ?? 0);
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

    $contractTitle = 'CONTRATO TESTE — ETAPA 16 DOSSIÊ';
    $rCt = postContract($http, ctPayload([
        'sponsor_id' => (string) $sponsorId,
        'company_id' => (string) $companyId,
        'contact_id' => (string) $contactId,
        'opportunity_id' => (string) $oppId,
        'proposal_id' => (string) $propId,
        'quota_id' => (string) $quotaId,
        'title' => $contractTitle,
        'formalized_value' => '50000',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+365 days')),
    ]));
    $contractId = preg_match('#/contracts/(\d+)#', (string) ($rCt['location'] ?? ''), $m) ? (int) $m[1]
        : (int) (dbq('SELECT id FROM contracts WHERE title = ? ORDER BY id DESC LIMIT 1', [$contractTitle])['id'] ?? 0);
    $contractId > 0 ? $R->pass('dados', 'Contrato teste', 'id=' . $contractId) : $R->fail('dados', 'Contrato teste');

    return compact('companyId', 'contactId', 'quotaId', 'oppId', 'propId', 'sponsorId', 'contractId');
}

function archiveTestData(Report $R): void
{
    echo PHP_EOL . "12. LIMPEZA LOCAL" . PHP_EOL;
    $now = date('Y-m-d H:i:s');
    $di = db()->exec("UPDATE sponsor_dossier_items SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE '%ETAPA 16%'");
    $sd = db()->exec("UPDATE sponsor_dossiers SET archived_at='{$now}' WHERE archived_at IS NULL AND (title LIKE 'DOSSIÊ TESTE ETAPA 16%' OR title LIKE 'ETAPA 16 DOSSIÊ%')");
    $doc = db()->exec("UPDATE documents SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE '%ETAPA 16%'");
    $sp = db()->exec("UPDATE sponsors SET archived_at='{$now}' WHERE archived_at IS NULL AND (sponsor_display_name LIKE 'ETAPA 16 DOSSIÊ%' OR sponsor_display_name LIKE 'PATROCINADOR BASE ETAPA 16%')");
    $co = db()->exec("UPDATE companies SET archived_at='{$now}' WHERE archived_at IS NULL AND name LIKE 'ETAPA 16 DOSSIÊ%'");
    $ct = db()->exec("UPDATE contacts SET archived_at='{$now}' WHERE archived_at IS NULL AND name LIKE 'ETAPA 16 DOSSIÊ%'");
    $op = db()->exec("UPDATE opportunities SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE 'ETAPA 16 DOSSIÊ%'");
    $pr = db()->exec("UPDATE proposals SET archived_at='{$now}' WHERE archived_at IS NULL AND title LIKE 'ETAPA 16 DOSSIÊ%'");
    db()->prepare('UPDATE users SET status=? WHERE email=?')->execute(['inactive', 'sem-dossiers@test.com']);
    $R->pass('limpeza', 'Dados de teste arquivados/inativados', "di={$di} sd={$sd} doc={$doc}");
}

function resolveDossierId(array $res, string $title): int
{
    if (preg_match('#/sponsor-dossiers/(\d+)#', (string) ($res['location'] ?? ''), $m)) {
        return (int) $m[1];
    }
    return (int) (dbq('SELECT id FROM sponsor_dossiers WHERE title = ? ORDER BY id DESC LIMIT 1', [$title])['id'] ?? 0);
}

echo "=== VALIDAÇÃO ETAPA 16 PRODUÇÃO — DOSSIÊS / PRESTAÇÃO COMERCIAL ===" . PHP_EOL . PHP_EOL;
$R = new Report();

echo "1. AMBIENTE / BANCO" . PHP_EOL;
$hasDossiersTable = (bool) dbq("SHOW TABLES LIKE 'sponsor_dossiers'");
$hasItemsTable = (bool) dbq("SHOW TABLES LIKE 'sponsor_dossier_items'");
$hasDocCol = (bool) dbq("SHOW COLUMNS FROM documents LIKE 'sponsor_dossier_id'");
$hasDossiersTable
    ? $R->pass('ambiente', 'Tabela sponsor_dossiers', 'OK')
    : $R->fail('ambiente', 'Tabela sponsor_dossiers', 'MISSING');
$hasItemsTable
    ? $R->pass('ambiente', 'Tabela sponsor_dossier_items', 'OK')
    : $R->fail('ambiente', 'Tabela sponsor_dossier_items', 'MISSING');
$hasDocCol
    ? $R->pass('ambiente', 'Coluna documents.sponsor_dossier_id', 'OK')
    : $R->fail('ambiente', 'Coluna documents.sponsor_dossier_id', 'MISSING');
if (!$hasDossiersTable || !$hasItemsTable || !$hasDocCol) {
    echo PHP_EOL . 'ABORT: execute a migration 2026_etapa16_sponsor_dossiers.sql antes da validação.' . PHP_EOL;
    exit(1);
}
$perms = db()->query("SELECT slug FROM permissions WHERE slug LIKE 'dossiers.%' ORDER BY slug")->fetchAll(PDO::FETCH_COLUMN);
(count($perms) === 8)
    ? $R->pass('ambiente', 'Permissões dossiers.* (8)', implode(', ', $perms))
    : $R->fail('ambiente', 'Permissões dossiers.*', implode(', ', $perms));
$capCreate = dbq(
    "SELECT COUNT(*) AS c FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug = 'captacao-comercial' AND p.slug = 'dossiers.create'"
);
((int) ($capCreate['c'] ?? 0) > 0)
    ? $R->pass('ambiente', 'Captação com dossiers.create')
    : $R->fail('ambiente', 'Captação com dossiers.create');
$capApprove = dbq(
    "SELECT COUNT(*) AS c FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug = 'captacao-comercial' AND p.slug = 'dossiers.approve'"
);
((int) ($capApprove['c'] ?? 0) === 0)
    ? $R->pass('ambiente', 'Captação sem dossiers.approve')
    : $R->fail('ambiente', 'Captação não deve ter approve');
$capDeliver = dbq(
    "SELECT COUNT(*) AS c FROM role_permissions rp
     INNER JOIN roles r ON r.id = rp.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE r.slug = 'captacao-comercial' AND p.slug = 'dossiers.deliver'"
);
((int) ($capDeliver['c'] ?? 0) > 0)
    ? $R->pass('ambiente', 'Captação com dossiers.deliver')
    : $R->fail('ambiente', 'Captação com dossiers.deliver');
foreach (['producao-coordenacao', 'comunicacao'] as $roleSlug) {
    $allowed = db()->prepare(
        "SELECT p.slug FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         WHERE r.slug = ? AND p.slug LIKE 'dossiers.%' ORDER BY p.slug"
    );
    $allowed->execute([$roleSlug]);
    $slugs = $allowed->fetchAll(PDO::FETCH_COLUMN);
    ($slugs === ['dossiers.edit', 'dossiers.generate', 'dossiers.view'])
        ? $R->pass('ambiente', "{$roleSlug} só view/edit/generate")
        : $R->fail('ambiente', "{$roleSlug} permissões", implode(', ', $slugs));
}
foreach (['leitura-consulta'] as $roleSlug) {
    $cnt = dbq(
        "SELECT COUNT(*) AS c FROM role_permissions rp
         INNER JOIN roles r ON r.id = rp.role_id
         INNER JOIN permissions p ON p.id = rp.permission_id
         WHERE r.slug = ? AND p.slug LIKE 'dossiers.%' AND p.slug != 'dossiers.view'",
        [$roleSlug]
    );
    ((int) ($cnt['c'] ?? 0) === 0)
        ? $R->pass('ambiente', "{$roleSlug} só dossiers.view")
        : $R->fail('ambiente', "{$roleSlug} permissões extras");
}

echo PHP_EOL . "2. AUTENTICAÇÃO E PERMISSÃO" . PHP_EOL;
$anon = new HttpClient();
$r = $anon->get('/sponsor-dossiers');
($r['code'] === 302 && str_contains((string) $r['location'], '/login'))
    ? $R->pass('auth', 'GET /sponsor-dossiers sem login → 302')
    : $R->fail('auth', 'GET /sponsor-dossiers sem login → 302', 'code=' . $r['code']);

ensureSemDossiersUser();
$sem = new HttpClient();
$sem->login('sem-dossiers@test.com', PASSWORD);
$r = $sem->get('/sponsor-dossiers');
($r['code'] === 403) ? $R->pass('auth', 'GET /sponsor-dossiers sem dossiers.view → 403') : $R->fail('auth', 'sem view → 403', 'code=' . $r['code']);

$admin = new HttpClient();
$admin->login('admin@dancacarajas.com', PASSWORD);
$r = $admin->get('/sponsor-dossiers');
($r['code'] === 200) ? $R->pass('auth', 'GET /sponsor-dossiers admin → 200') : $R->fail('auth', 'admin list', 'code=' . $r['code']);

$menu = $admin->get('/dashboard');
str_contains($menu['body'], 'Dossiês')
    ? $R->pass('auth', 'Menu Dossiês visível com dossiers.view')
    : $R->fail('auth', 'Menu Dossiês');
$semMenu = $sem->get('/dashboard');
!preg_match('#href=["\'][^"\']*/sponsor-dossiers["\']#', $semMenu['body'])
    ? $R->pass('auth', 'Menu Dossiês oculto sem dossiers.view')
    : $R->fail('auth', 'Menu oculto sem view');

$r = $sem->get('/sponsor-dossiers/create');
($r['code'] === 403) ? $R->pass('auth', 'GET /sponsor-dossiers/create sem create → 403') : $R->fail('auth', 'create sem perm', 'code=' . $r['code']);

$badCsrf = new HttpClient();
$badCsrf->login('admin@dancacarajas.com', PASSWORD);
$r = $badCsrf->post('/sponsor-dossiers', ['_csrf' => 'x', 'sponsor_id' => '1', 'title' => 'Teste CSRF']);
($r['code'] === 419) ? $R->pass('auth', 'POST CSRF inválido → 419') : $R->fail('auth', 'CSRF', 'code=' . $r['code']);

$cap = new HttpClient();
if ($cap->login('captacao@test.com', PASSWORD)) {
    $r = $cap->get('/sponsor-dossiers');
    ($r['code'] === 200) ? $R->pass('auth', 'Captação lista dossiês → 200') : $R->fail('auth', 'captação list', 'code=' . $r['code']);
} else {
    $R->fail('auth', 'Login captacao@test.com');
}

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
    ['sem patrocinador', dossierPayload(['title' => 'Teste sem sponsor'])],
    ['patrocinador inexistente', dossierPayload(['sponsor_id' => '999999', 'title' => 'Teste sponsor inválido'])],
    ['sem título curto', dossierPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'AB'])],
    ['tipo inválido', dossierPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste tipo', 'dossier_type' => 'invalido'])],
    ['status inválido', dossierPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste st', 'status' => 'invalido'])],
    ['entrega inválida', dossierPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste ent', 'delivery_status' => 'invalido'])],
    ['periodo inicio inválido', dossierPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste pi', 'period_start' => '99/99/9999'])],
    ['periodo fim inválido', dossierPayload(['sponsor_id' => (string) $sponsorId, 'title' => 'Teste pf', 'period_end' => '99/99/9999'])],
    ['fim < inicio', dossierPayload([
        'sponsor_id' => (string) $sponsorId, 'title' => 'Teste periodo',
        'period_start' => '2026-12-01', 'period_end' => '2026-01-01',
    ])],
];
foreach ($validationTests as [$label, $payload]) {
    $r = postDossier($admin, $payload);
    ($r['code'] === 422)
        ? $R->pass('validacao', "POST inválido: {$label} → 422")
        : $R->fail('validacao', $label, 'code=' . $r['code']);
}

$testTitle = 'DOSSIÊ TESTE ETAPA 16 ' . date('His');
$mainDocId = 0;
echo PHP_EOL . "4. CRIAÇÃO VÁLIDA E CRUD" . PHP_EOL;
$r = postDossier($admin, dossierPayload([
    'sponsor_id' => (string) $sponsorId,
    'company_id' => (string) ($sp['company_id'] ?? ''),
    'contact_id' => (string) ($sp['contact_id'] ?? ''),
    'opportunity_id' => (string) ($sp['opportunity_id'] ?? ''),
    'proposal_id' => (string) ($sp['proposal_id'] ?? ''),
    'quota_id' => (string) ($sp['quota_id'] ?? ''),
    'main_contract_id' => $contractId > 0 ? (string) $contractId : '',
    'title' => $testTitle,
    'dossier_number' => 'DOS-16-' . date('His'),
    'period_start' => date('Y-m-d', strtotime('-90 days')),
    'period_end' => date('Y-m-d'),
    'executive_summary' => 'Resumo executivo teste etapa 16',
]));
$dossierId = resolveDossierId($r, $testTitle);
if ($dossierId > 0) {
    $R->pass('crud', 'POST válido → redirect show', 'id=' . $dossierId);
} else {
    $R->fail('crud', 'POST válido', 'code=' . $r['code']);
}
$R->ids['dossier_id'] = $dossierId;

if ($dossierId > 0) {
    $sd = dbq('SELECT sponsor_id, company_id, dossier_type, status FROM sponsor_dossiers WHERE id=?', [$dossierId]);
    ((int) $sd['sponsor_id'] === $sponsorId && (int) $sd['company_id'] === (int) ($sp['company_id'] ?? 0))
        ? $R->pass('crud', 'Vínculos herdados do sponsor')
        : $R->fail('crud', 'Vínculos herdados');
    ($sd['dossier_type'] === 'prestacao_comercial' && $sd['status'] === 'rascunho')
        ? $R->pass('crud', 'Tipo e status padrão na criação')
        : $R->fail('crud', 'defaults', json_encode($sd));

    $r = $admin->get('/sponsor-dossiers/' . $dossierId);
    ($r['code'] === 200) ? $R->pass('crud', 'GET show → 200') : $R->fail('crud', 'GET show', 'code=' . $r['code']);

    $r = $admin->get('/sponsor-dossiers/' . $dossierId . '/edit');
    ($r['code'] === 200) ? $R->pass('crud', 'GET edit → 200') : $R->fail('crud', 'GET edit', 'code=' . $r['code']);

    $editPage = $admin->get('/sponsor-dossiers/' . $dossierId . '/edit');
    $csrf = HttpClient::extractCsrf($editPage['body']);
    $updatedTitle = $testTitle . ' ATUALIZADO';
    $r = $admin->post('/sponsor-dossiers/' . $dossierId . '/update', array_merge(dossierPayload([
        'sponsor_id' => (string) $sponsorId,
        'title' => $updatedTitle,
        'status' => 'em_preparacao',
        'commercial_summary' => 'Resumo comercial atualizado',
    ]), ['_csrf' => $csrf ?? '']));
    ($r['code'] === 302) ? $R->pass('crud', 'POST update → redirect') : $R->fail('crud', 'POST update', 'code=' . $r['code']);
    $log = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier' AND entity_id=? AND action='sponsor_dossier_updated' ORDER BY id DESC LIMIT 1", [$dossierId]);
    $log ? $R->pass('crud', 'Log sponsor_dossier_updated') : $R->fail('crud', 'Log sponsor_dossier_updated');

    echo PHP_EOL . "5. GERAR, APROVAR, ENTREGAR E STATUS" . PHP_EOL;
    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $rGen = $admin->post('/sponsor-dossiers/' . $dossierId . '/generate', ['_csrf' => $csrf ?? '']);
    in_array($rGen['code'], [302, 303], true)
        ? $R->pass('generate', 'POST generate → redirect')
        : $R->fail('generate', 'POST generate', 'code=' . $rGen['code']);
    $gen = dbq('SELECT status, generated_at, generated_by, contracts_count FROM sponsor_dossiers WHERE id=?', [$dossierId]);
    (!empty($gen['generated_at']) && !empty($gen['generated_by']))
        ? $R->pass('generate', 'Gerar consolidação preenche generated_at/generated_by')
        : $R->fail('generate', 'generate', json_encode($gen));
    ((int) ($gen['contracts_count'] ?? 0) >= 0)
        ? $R->pass('generate', 'Snapshot de métricas após generate')
        : $R->fail('generate', 'métricas');

    if ($cap->login('captacao@test.com', PASSWORD)) {
        $showCap = $cap->get('/sponsor-dossiers/' . $dossierId);
        $csrfCap = HttpClient::extractCsrf($showCap['body']);
        $rCapApprove = $cap->post('/sponsor-dossiers/' . $dossierId . '/approve', ['_csrf' => $csrfCap, 'approval_notes' => 'Tentativa cap']);
        ($rCapApprove['code'] === 403)
            ? $R->pass('approve', 'Captação sem dossiers.approve → 403')
            : $R->fail('approve', 'captação approve', 'code=' . $rCapApprove['code']);
        $rCapGen = $cap->post('/sponsor-dossiers/' . $dossierId . '/generate', ['_csrf' => $csrfCap]);
        in_array($rCapGen['code'], [302, 303], true)
            ? $R->pass('generate', 'Captação pode gerar consolidação')
            : $R->fail('generate', 'captação generate', 'code=' . $rCapGen['code']);
    }

    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/sponsor-dossiers/' . $dossierId . '/approve', [
        '_csrf' => $csrf,
        'approval_notes' => 'Aprovação interna teste etapa 16',
    ]);
    $ap = dbq('SELECT status, approved_at, approved_by FROM sponsor_dossiers WHERE id=?', [$dossierId]);
    ($ap['status'] === 'aprovado' && !empty($ap['approved_at']) && !empty($ap['approved_by']))
        ? $R->pass('approve', 'Aprovar preenche approved_at/approved_by')
        : $R->fail('approve', 'Aprovar', json_encode($ap));
    $logAp = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier' AND entity_id=? AND action='sponsor_dossier_approved' ORDER BY id DESC LIMIT 1", [$dossierId]);
    $logAp ? $R->pass('approve', 'Log sponsor_dossier_approved') : $R->fail('approve', 'Log approved');

    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/sponsor-dossiers/' . $dossierId . '/deliver', [
        '_csrf' => $csrf,
        'delivery_status' => 'entregue_patrocinador',
        'delivered_at' => date('Y-m-d H:i'),
        'delivery_notes' => 'Entrega teste etapa 16',
    ]);
    $del = dbq('SELECT status, delivery_status, delivered_at, delivered_by FROM sponsor_dossiers WHERE id=?', [$dossierId]);
    ($del['status'] === 'entregue' && $del['delivery_status'] === 'entregue_patrocinador' && !empty($del['delivered_at']))
        ? $R->pass('deliver', 'Entregar atualiza status e delivery_status')
        : $R->fail('deliver', 'Entregar', json_encode($del));
    $logDel = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier' AND entity_id=? AND action='sponsor_dossier_delivered' ORDER BY id DESC LIMIT 1", [$dossierId]);
    $logDel ? $R->pass('deliver', 'Log sponsor_dossier_delivered') : $R->fail('deliver', 'Log delivered');

    dbexec("UPDATE sponsor_dossiers SET status='em_revisao', delivery_status='preparando_entrega' WHERE id=?", [$dossierId]);
    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/sponsor-dossiers/' . $dossierId . '/status', [
        '_csrf' => $csrf,
        'status' => 'pendente',
        'delivery_status' => 'pendente_retorno',
        'notes' => 'Status teste etapa 16',
    ]);
    $stRow = dbq('SELECT status, delivery_status FROM sponsor_dossiers WHERE id=?', [$dossierId]);
    ($stRow['status'] === 'pendente' && $stRow['delivery_status'] === 'pendente_retorno')
        ? $R->pass('status', 'POST status altera status e delivery_status')
        : $R->fail('status', 'status', json_encode($stRow));
    $logSt = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier' AND entity_id=? AND action='sponsor_dossier_status_changed' ORDER BY id DESC LIMIT 1", [$dossierId]);
    $logSt ? $R->pass('status', 'Log sponsor_dossier_status_changed') : $R->fail('status', 'Log status');

    echo PHP_EOL . "6. ITENS DO DOSSIÊ" . PHP_EOL;
    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $itemTitle = 'ITEM MANUAL ETAPA 16 ' . date('His');
    $beforeItems = (int) dbq('SELECT COUNT(*) AS c FROM sponsor_dossier_items WHERE dossier_id=?', [$dossierId])['c'];
    $admin->post('/sponsor-dossiers/' . $dossierId . '/items', [
        '_csrf' => $csrf,
        'title' => 'AB',
        'item_type' => 'manual',
        'status' => 'ativo',
    ]);
    $afterBad = (int) dbq('SELECT COUNT(*) AS c FROM sponsor_dossier_items WHERE dossier_id=?', [$dossierId])['c'];
    ($afterBad === $beforeItems)
        ? $R->pass('items', 'Item inválido (título curto) não criado')
        : $R->fail('items', 'validação item título');

    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/sponsor-dossiers/' . $dossierId . '/items', [
        '_csrf' => $csrf,
        'title' => $itemTitle,
        'item_type' => 'manual',
        'source_module' => 'manual',
        'status' => 'ativo',
        'evidence_status' => 'nao_aplicavel',
        'amount' => '1500',
        'date_ref' => date('Y-m-d'),
        'description' => 'Item manual de teste',
        'sort_order' => '10',
    ]);
    $itemId = (int) (dbq('SELECT id FROM sponsor_dossier_items WHERE dossier_id=? AND title=? ORDER BY id DESC LIMIT 1', [$dossierId, $itemTitle])['id'] ?? 0);
    $itemId > 0 ? $R->pass('items', 'POST item manual criado', 'id=' . $itemId) : $R->fail('items', 'Criar item manual');
    $logItem = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier_item' AND entity_id=? AND action='sponsor_dossier_item_created' ORDER BY id DESC LIMIT 1", [$itemId]);
    $logItem ? $R->pass('items', 'Log sponsor_dossier_item_created') : $R->fail('items', 'Log item created');

    if ($itemId > 0) {
        $updatedItemTitle = $itemTitle . ' EDITADO';
        $show = $admin->get('/sponsor-dossiers/' . $dossierId);
        $csrf = HttpClient::extractCsrf($show['body']);
        $admin->post('/sponsor-dossiers/' . $dossierId . '/items/' . $itemId . '/update', [
            '_csrf' => $csrf,
            'title' => $updatedItemTitle,
            'item_type' => 'observacao',
            'status' => 'conferido',
            'evidence_status' => 'pendente',
            'amount' => '2000',
        ]);
        $itemRow = dbq('SELECT title, item_type, status FROM sponsor_dossier_items WHERE id=?', [$itemId]);
        ($itemRow['title'] === $updatedItemTitle && $itemRow['item_type'] === 'observacao' && $itemRow['status'] === 'conferido')
            ? $R->pass('items', 'POST update item')
            : $R->fail('items', 'update item', json_encode($itemRow));
        $logItemUp = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier_item' AND entity_id=? AND action='sponsor_dossier_item_updated' ORDER BY id DESC LIMIT 1", [$itemId]);
        $logItemUp ? $R->pass('items', 'Log sponsor_dossier_item_updated') : $R->fail('items', 'Log item updated');

        $show = $admin->get('/sponsor-dossiers/' . $dossierId);
        $csrf = HttpClient::extractCsrf($show['body']);
        $admin->post('/sponsor-dossiers/' . $dossierId . '/items/' . $itemId . '/archive', ['_csrf' => $csrf]);
        !empty(dbq('SELECT archived_at FROM sponsor_dossier_items WHERE id=?', [$itemId])['archived_at'])
            ? $R->pass('items', 'archive item preenche archived_at')
            : $R->fail('items', 'archive item');
        $show = $admin->get('/sponsor-dossiers/' . $dossierId);
        $csrf = HttpClient::extractCsrf($show['body']);
        $admin->post('/sponsor-dossiers/' . $dossierId . '/items/' . $itemId . '/restore', ['_csrf' => $csrf]);
        empty(dbq('SELECT archived_at FROM sponsor_dossier_items WHERE id=?', [$itemId])['archived_at'])
            ? $R->pass('items', 'restore item limpa archived_at')
            : $R->fail('items', 'restore item');
    }

    echo PHP_EOL . "7. ARQUIVAR / RESTAURAR DOSSIÊ" . PHP_EOL;
    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/sponsor-dossiers/' . $dossierId . '/archive', ['_csrf' => $csrf]);
    !empty(dbq('SELECT archived_at FROM sponsor_dossiers WHERE id=?', [$dossierId])['archived_at'])
        ? $R->pass('archive', 'archive preenche archived_at')
        : $R->fail('archive', 'archived_at');
    $list = $admin->get('/sponsor-dossiers');
    !str_contains($list['body'], $updatedTitle)
        ? $R->pass('archive', 'Some da listagem padrão')
        : $R->fail('archive', 'listagem padrão');
    $archList = $admin->get('/sponsor-dossiers?show_archived=1');
    str_contains($archList['body'], $updatedTitle)
        ? $R->pass('archive', 'Filtro arquivados mostra')
        : $R->fail('archive', 'filtro arquivados');
    $show = $admin->get('/sponsor-dossiers/' . $dossierId);
    $csrf = HttpClient::extractCsrf($show['body']);
    $admin->post('/sponsor-dossiers/' . $dossierId . '/restore', ['_csrf' => $csrf]);
    empty(dbq('SELECT archived_at FROM sponsor_dossiers WHERE id=?', [$dossierId])['archived_at'])
        ? $R->pass('archive', 'restore limpa archived_at')
        : $R->fail('archive', 'restore');
    $logArch = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier' AND entity_id=? AND action='sponsor_dossier_archived' ORDER BY id DESC LIMIT 1", [$dossierId]);
    $logArch ? $R->pass('archive', 'Log sponsor_dossier_archived') : $R->fail('archive', 'Log archived');
    $logRest = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier' AND entity_id=? AND action='sponsor_dossier_restored' ORDER BY id DESC LIMIT 1", [$dossierId]);
    $logRest ? $R->pass('archive', 'Log sponsor_dossier_restored') : $R->fail('archive', 'Log restored');

    echo PHP_EOL . "8. DOCUMENTOS VINCULADOS" . PHP_EOL;
    $docPath = sys_get_temp_dir() . '/doc_test_etapa16.txt';
    file_put_contents($docPath, 'Documento teste validacao etapa 16');
    $docPage = $admin->get('/sponsor-dossiers/' . $dossierId . '/documents/create');
    ($docPage['code'] === 200 && str_contains($docPage['body'], (string) $dossierId))
        ? $R->pass('docs', 'GET /sponsor-dossiers/{id}/documents/create → 200')
        : $R->fail('docs', 'create contextual', 'code=' . $docPage['code']);

    $uploadDoc = static function (HttpClient $http, int $did, int $sid, array $sp, string $title, string $flag): int {
        $docPage = $http->get('/sponsor-dossiers/' . $did . '/documents/create');
        $docCsrf = HttpClient::extractCsrf($docPage['body']);
        $post = [
            '_csrf' => $docCsrf,
            'sponsor_dossier_id' => (string) $did,
            'sponsor_id' => (string) $sid,
            'company_id' => (string) ($sp['company_id'] ?? ''),
            'title' => $title,
            'category' => 'documento_comercial',
            'status' => 'ativo',
            'access_level' => 'interno',
            'document_date' => date('Y-m-d'),
            $flag => '1',
        ];
        $docPath = sys_get_temp_dir() . '/doc_test_etapa16.txt';
        $docRes = $http->post('/documents', $post, 'document_file', $docPath);
        if (preg_match('#/documents/(\d+)#', (string) ($docRes['location'] ?? ''), $dm)) {
            return (int) $dm[1];
        }
        return (int) (dbq('SELECT id FROM documents WHERE title = ? ORDER BY id DESC LIMIT 1', [$title])['id'] ?? 0);
    };

    $mainTitle = 'DOC MAIN ETAPA 16';
    $mainDocId = $uploadDoc($admin, $dossierId, $sponsorId, $sp, $mainTitle, 'use_as_dossier_main');
    $mainDocId > 0 ? $R->pass('docs', 'Documento use_as_dossier_main', 'id=' . $mainDocId) : $R->fail('docs', 'Upload main');
    if ($mainDocId > 0) {
        $d = dbq('SELECT sponsor_dossier_id FROM documents WHERE id=?', [$mainDocId]);
        ((int) ($d['sponsor_dossier_id'] ?? 0) === $dossierId)
            ? $R->pass('docs', 'documents.sponsor_dossier_id salvo')
            : $R->fail('docs', 'sponsor_dossier_id');
        $linked = dbq('SELECT main_document_id FROM sponsor_dossiers WHERE id=?', [$dossierId]);
        ((int) ($linked['main_document_id'] ?? 0) === $mainDocId)
            ? $R->pass('docs', 'use_as_dossier_main vincula main_document_id')
            : $R->fail('docs', 'main_document_id');
    }

    $finalTitle = 'DOC FINAL ETAPA 16';
    $finalDocId = $uploadDoc($admin, $dossierId, $sponsorId, $sp, $finalTitle, 'use_as_dossier_final');
    $finalDocId > 0 ? $R->pass('docs', 'use_as_dossier_final vincula final', 'id=' . $finalDocId) : $R->fail('docs', 'use_as_dossier_final');
    if ($finalDocId > 0) {
        $linked = dbq('SELECT final_document_id FROM sponsor_dossiers WHERE id=?', [$dossierId]);
        ((int) ($linked['final_document_id'] ?? 0) === $finalDocId)
            ? $R->pass('docs', 'final_document_id salvo')
            : $R->fail('docs', 'final_document_id');
    }

    $receiptTitle = 'DOC RECEIPT ETAPA 16';
    $receiptDocId = $uploadDoc($admin, $dossierId, $sponsorId, $sp, $receiptTitle, 'use_as_dossier_delivery_receipt');
    $receiptDocId > 0 ? $R->pass('docs', 'use_as_dossier_delivery_receipt', 'id=' . $receiptDocId) : $R->fail('docs', 'delivery receipt');
    if ($receiptDocId > 0) {
        $linked = dbq('SELECT delivery_receipt_document_id FROM sponsor_dossiers WHERE id=?', [$dossierId]);
        ((int) ($linked['delivery_receipt_document_id'] ?? 0) === $receiptDocId)
            ? $R->pass('docs', 'delivery_receipt_document_id salvo')
            : $R->fail('docs', 'delivery_receipt_document_id');
        $showDoc = $admin->get('/documents/' . $receiptDocId);
        str_contains($showDoc['body'], '/sponsor-dossiers/' . $dossierId)
            ? $R->pass('docs', 'Show documento link dossiê')
            : $R->fail('docs', 'Show documento link');
    }

    $showSd = $admin->get('/sponsor-dossiers/' . $dossierId);
    str_contains($showSd['body'], 'Documentos vinculados') || str_contains($showSd['body'], 'Documentos do patrocinador')
        ? $R->pass('docs', 'Show dossiê bloco documentos')
        : $R->fail('docs', 'bloco documentos dossiê');
}

echo PHP_EOL . "9. FILTROS E PAGINAÇÃO" . PHP_EOL;
$chain = ensureFullTestChain($admin, $R);
$R->ids = array_merge($R->ids, $chain);
$filters = [
    'busca' => '/sponsor-dossiers?q=DOSSIÊ',
    'patrocinador' => '/sponsor-dossiers?sponsor_id=' . $sponsorId,
    'empresa' => '/sponsor-dossiers?company_id=' . (int) ($sp['company_id'] ?? 0),
    'contato' => '/sponsor-dossiers?contact_id=' . (int) ($chain['contactId'] ?? 0),
    'oportunidade' => '/sponsor-dossiers?opportunity_id=' . (int) ($chain['oppId'] ?? 0),
    'proposta' => '/sponsor-dossiers?proposal_id=' . (int) ($chain['propId'] ?? 0),
    'cota' => '/sponsor-dossiers?quota_id=' . (int) ($chain['quotaId'] ?? 0),
    'contrato_main' => '/sponsor-dossiers?main_contract_id=' . (int) ($chain['contractId'] ?? $contractId),
    'contrato_alias' => '/sponsor-dossiers?contract_id=' . (int) ($chain['contractId'] ?? $contractId),
    'tipo' => '/sponsor-dossiers?dossier_type=prestacao_comercial',
    'status' => '/sponsor-dossiers?status=rascunho',
    'entrega' => '/sponsor-dossiers?delivery_status=nao_entregue',
    'responsavel' => '/sponsor-dossiers?responsible_user_id=1',
    'periodo_de' => '/sponsor-dossiers?period_from=' . date('Y-m-d', strtotime('-365 days')),
    'periodo_ate' => '/sponsor-dossiers?period_to=' . date('Y-m-d'),
    'aprovados' => '/sponsor-dossiers?approved=1',
    'entregues' => '/sponsor-dossiers?delivered=1',
    'pendentes' => '/sponsor-dossiers?pending=1',
    'com_saldo' => '/sponsor-dossiers?with_balance=1',
    'contrap_pendentes' => '/sponsor-dossiers?pending_counterparts=1',
    'contrap_atrasadas' => '/sponsor-dossiers?overdue_counterparts=1',
    'arquivados' => '/sponsor-dossiers?show_archived=1',
    'pagina_2' => '/sponsor-dossiers?page=2',
    'doc_dossier' => '/documents?sponsor_dossier_id=' . (int) ($R->ids['dossier_id'] ?? 0),
];
foreach ($filters as $name => $url) {
    if (preg_match('#=(0)(?:&|$)#', $url) || str_contains($url, 'sponsor_dossier_id=0')) {
        continue;
    }
    $r = $admin->get($url);
    ($r['code'] === 200) ? $R->pass('filtros', "Filtro {$name} → 200") : $R->fail('filtros', $name, 'code=' . $r['code']);
}
($admin->get('/sponsor-dossiers')['code'] === 200)
    ? $R->pass('filtros', 'Limpar filtros (/sponsor-dossiers) → 200')
    : $R->fail('filtros', 'limpar filtros');

echo PHP_EOL . "10. BLOCOS CONTEXTUAIS, IMPRESSÃO E DASHBOARD" . PHP_EOL;
foreach ([
    'sponsors' => $sponsorId,
    'contracts' => (int) ($chain['contractId'] ?? $contractId),
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
    $hasBlock = str_contains($r['body'], 'dossier-summary')
        && str_contains($r['body'], 'Ver todos')
        && (str_contains($r['body'], 'aprovado') || str_contains($r['body'], 'entregue') || str_contains($r['body'], 'pendente'));
    if ($r['code'] === 200 && $hasBlock) {
        $R->pass('contexto', "Bloco dossiê em /{$entity}/{$eid}");
    } else {
        $R->fail('contexto', "/{$entity}/{$eid}", 'code=' . $r['code'] . ' block=' . ($hasBlock ? '1' : '0'));
    }
    $createRoute = "/{$entity}/{$eid}/dossiers/create";
    ($admin->get($createRoute)['code'] === 200)
        ? $R->pass('contexto', "GET {$createRoute} → 200")
        : $R->fail('contexto', $createRoute);
}

if ($dossierId > 0) {
    $print = $admin->get('/sponsor-dossiers/' . $dossierId . '/print');
    ($print['code'] === 200 && (str_contains($print['body'], 'dossier-print') || str_contains($print['body'], 'Prestação de contas comercial')))
        ? $R->pass('print', 'GET print → 200 com layout dossier-print')
        : $R->fail('print', 'print', 'code=' . $print['code']);
    $logPrint = dbq("SELECT id FROM activity_logs WHERE entity_type='sponsor_dossier' AND entity_id=? AND action='sponsor_dossier_print_viewed' ORDER BY id DESC LIMIT 1", [$dossierId]);
    $logPrint ? $R->pass('print', 'Log sponsor_dossier_print_viewed') : $R->fail('print', 'Log print');
}

$dash = $admin->get('/dashboard');
foreach (['Dossiês', 'aprovado', 'entregue', 'pendente', 'contrap.', 'saldo', 'Gerenciar dossiês', 'Novo dossiê'] as $needle) {
    str_contains($dash['body'], $needle)
        ? $R->pass('dashboard', "Dashboard contém: {$needle}")
        : $R->fail('dashboard', $needle);
}

if ($dossierId > 0 && $mainDocId > 0) {
    $dl = $admin->get('/documents/' . $mainDocId . '/download');
    in_array($dl['code'], [200, 302], true)
        ? $R->pass('docs', 'Download protegido documento → ' . $dl['code'])
        : $R->fail('docs', 'download', 'code=' . $dl['code']);
}

echo PHP_EOL . "11. ESCOPO NÃO CRIADO" . PHP_EOL;
$futureRoutes = ['/portal', '/signatures', '/finance/reports', '/sponsor-portal'];
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
echo PHP_EOL . 'Validação Etapa 16 em produção concluída com sucesso.' . PHP_EOL;
