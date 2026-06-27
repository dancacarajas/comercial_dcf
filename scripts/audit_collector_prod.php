<?php

declare(strict_types=1);

/**
 * Auditoria operacional — captadores em produção.
 *
 * Valida o fluxo CRM direto e WordPress → CRM sem expor segredos.
 * Lê COLLECTOR_ENDPOINT_SECRET exclusivamente via bootstrap (.env).
 *
 * Uso (no servidor de produção):
 *   php scripts/audit_collector_prod.php
 *
 * Exit codes:
 *   0 — todos os critérios atendidos
 *   1 — falha de pré-requisito (secret ausente, endpoint desabilitado)
 *   2 — falha em uma ou mais verificações operacionais
 *
 * Registros de auditoria permanecem em collector_applications (não são apagados).
 */

$root = dirname(__DIR__);
require $root . '/app/Core/App.php';
(new App\Core\App($root))->boot();

$config = require $root . '/config/app.php';
$pdo    = App\Core\Database::connection();

/** @var list<string> */
$failures = [];

echo '=== AUDITORIA CAPTADORES PRODUÇÃO ===' . PHP_EOL;
echo 'mode=operational_audit' . PHP_EOL;
echo 'records_policy=keep (registros de auditoria não são removidos)' . PHP_EOL;

$headFile = $root . '/.git/HEAD';
if (is_file($headFile)) {
    $head = trim((string) file_get_contents($headFile));
    if (str_starts_with($head, 'ref:')) {
        $ref = trim(substr($head, 5));
        $refFile = $root . '/.git/' . $ref;
        $head = is_file($refFile) ? trim((string) file_get_contents($refFile)) : $head;
    }
    echo 'git_head=' . $head . PHP_EOL;
}

$enabled = !empty($config['collector_endpoint_enabled']);
$secret  = (string) ($config['collector_endpoint_secret'] ?? '');

echo 'collector_enabled=' . var_export($enabled, true) . PHP_EOL;
echo 'token_set=' . var_export($secret !== '', true) . PHP_EOL;
echo 'token_prefix=' . ($secret !== '' ? substr($secret, 0, 12) . '...' : 'EMPTY') . PHP_EOL;
echo 'token_length=' . strlen($secret) . PHP_EOL;

if (!$enabled) {
    echo 'FAIL=collector_endpoint_enabled está desabilitado' . PHP_EOL;
    exit(1);
}

if ($secret === '') {
    echo 'FAIL=COLLECTOR_ENDPOINT_SECRET ausente no runtime (.env)' . PHP_EOL;
    exit(1);
}

$stamp = gmdate('YmdHis');
$emailCrm = "audit.collector.prod+{$stamp}.crm@example.com";
$emailWp  = "audit.collector.prod+{$stamp}.wp@example.com";

echo 'audit_email_crm=' . $emailCrm . PHP_EOL;
echo 'audit_email_wp=' . $emailWp . PHP_EOL;

/**
 * @param array<string, mixed> $payload
 * @return array{status:int,body:string}
 */
function auditHttpPost(string $url, array $payload, array $headers = []): array
{
    $headerLines = ['Content-Type: application/json'];
    foreach ($headers as $name => $value) {
        $headerLines[] = $name . ': ' . $value;
    }

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headerLines) . "\r\n",
            'content'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ignore_errors' => true,
            'timeout'       => 25,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body   = (string) (@file_get_contents($url, false, $ctx) ?: '');
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    return ['status' => $status, 'body' => $body];
}

/** @return array<string, mixed> */
function auditPayload(string $email, string $channel): array
{
    return [
        'name'                        => 'Auditoria Operacional Captador',
        'company_or_activity'         => 'Auditoria técnica',
        'document_number'             => '52998224725',
        'email'                       => $email,
        'phone_whatsapp'              => '94988887777',
        'city_state'                  => 'Parauapebas/PA',
        'rouanet_experience'          => 'intermediaria',
        'segments'                    => 'Cultura',
        'sponsor_network_description' => 'Registro gerado por audit_collector_prod.php',
        'message'                     => 'Teste operacional (' . $channel . ') — ' . gmdate('c'),
        'consent_contact'             => '1',
        'source_page'                 => 'patrocinio/captadores-de-recursos',
        'source_url'                  => 'https://dancacarajas.com.br/patrocinio/captadores-de-recursos/',
    ];
}

/** @return array<string, mixed>|null */
function fetchAuditRow(PDO $pdo, string $email): ?array
{
    $st = $pdo->prepare(
        'SELECT id, name, email, source, source_page, status, consent_contact, consent_lgpd_at, created_at
           FROM collector_applications WHERE email = ? ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/** @return list<string> */
function validateAuditRow(?array $row, string $email): array
{
    $errors = [];
    if ($row === null) {
        $errors[] = "registro ausente para {$email}";

        return $errors;
    }

    if ((string) ($row['email'] ?? '') !== $email) {
        $errors[] = 'email divergente';
    }
    if ((string) ($row['status'] ?? '') !== 'manifestacao_recebida') {
        $errors[] = 'status != manifestacao_recebida';
    }
    if ((int) ($row['consent_contact'] ?? 0) !== 1) {
        $errors[] = 'consent_contact != 1';
    }
    if ((string) ($row['source'] ?? '') !== 'site') {
        $errors[] = 'source != site';
    }
    if ((string) ($row['source_page'] ?? '') !== 'patrocinio/captadores-de-recursos') {
        $errors[] = 'source_page != patrocinio/captadores-de-recursos';
    }
    if (empty($row['consent_lgpd_at'])) {
        $errors[] = 'consent_lgpd_at vazio';
    }

    return $errors;
}

$crmUrl = rtrim((string) ($config['url'] ?? ''), '/') . '/api/collectors/site';
$wpUrl  = rtrim((string) (getenv('COLLECTOR_WP_PROXY_URL') ?: 'https://dancacarajas.com.br/wp-json/dcx-crm/v1/collector-application'), '/');

// ── Teste 1: CRM direto (runtime PHP + secret do .env) ─────────────────────
$crmPayload = auditPayload($emailCrm, 'crm_direct');
$crmResult  = auditHttpPost($crmUrl, $crmPayload, ['X-DCF-Collector-Token' => $secret]);

echo 'crm_test_url=' . $crmUrl . PHP_EOL;
echo 'crm_test_status=' . $crmResult['status'] . PHP_EOL;
echo 'crm_test_body=' . trim($crmResult['body']) . PHP_EOL;

if ($crmResult['status'] !== 201) {
    $failures[] = 'CRM direct HTTP != 201';
}

$crmRow = fetchAuditRow($pdo, $emailCrm);
echo 'crm_audit_row=' . json_encode($crmRow, JSON_UNESCAPED_UNICODE) . PHP_EOL;
foreach (validateAuditRow($crmRow, $emailCrm) as $err) {
    $failures[] = 'CRM DB: ' . $err;
}

// ── Teste 2: Proxy WordPress (sem token no cliente) ─────────────────────────
$wpPayload = [
    'nome'                 => 'Auditoria Operacional Captador',
    'empresa'              => 'Auditoria técnica',
    'documento'            => '52998224725',
    'email'                => $emailWp,
    'whatsapp'             => '94988887766',
    'cidade_uf'            => 'Parauapebas/PA',
    'experiencia_rouanet'  => 'intermediaria',
    'segmentos'            => 'Cultura',
    'carteira'             => 'Registro gerado por audit_collector_prod.php',
    'mensagem'             => 'Teste operacional (wp_proxy) — ' . gmdate('c'),
    'autorizacao_contato'  => '1',
    'source_page'          => 'patrocinio/captadores-de-recursos',
    'source_url'           => 'https://dancacarajas.com.br/patrocinio/captadores-de-recursos/',
];

$wpResult = auditHttpPost($wpUrl, $wpPayload);

echo 'wp_proxy_url=' . $wpUrl . PHP_EOL;
echo 'wp_proxy_status=' . $wpResult['status'] . PHP_EOL;
echo 'wp_proxy_body=' . trim($wpResult['body']) . PHP_EOL;

$wpJson = json_decode($wpResult['body'], true);
if ($wpResult['status'] !== 201 || !is_array($wpJson) || ($wpJson['success'] ?? false) !== true) {
    $failures[] = 'WordPress proxy HTTP != 201 ou success != true';
}

$wpRow = fetchAuditRow($pdo, $emailWp);
echo 'wp_audit_row=' . json_encode($wpRow, JSON_UNESCAPED_UNICODE) . PHP_EOL;
foreach (validateAuditRow($wpRow, $emailWp) as $err) {
    $failures[] = 'WP DB: ' . $err;
}

echo 'total_collectors=' . $pdo->query('SELECT COUNT(*) FROM collector_applications')->fetchColumn() . PHP_EOL;

if ($failures === []) {
    echo 'RESULT=PASS (CRM 201 + WP proxy 201 + persistência validada)' . PHP_EOL;
    echo 'NOTE=Registros de auditoria mantidos em collector_applications para rastreabilidade.' . PHP_EOL;
    exit(0);
}

echo 'RESULT=FAIL' . PHP_EOL;
foreach ($failures as $failure) {
    echo 'FAIL=' . $failure . PHP_EOL;
}

exit(2);
