<?php

declare(strict_types=1);

/**
 * Harness HTTP — Etapa 9 (Leads do Site + API pública).
 * Uso: php tools/validate_etapa9.php
 */

$base = getenv('TEST_BASE') ?: 'http://localhost:8080';
$token = getenv('LEAD_TOKEN') ?: 'dcf-local-lead-token-2026-trocar-em-producao';
$adminEmail = getenv('TEST_ADMIN_EMAIL') ?: 'admin@dancacarajas.com';
$adminPass = getenv('TEST_ADMIN_PASS') ?: 'Mudar@123';

$results = [];
$cookieJar = tempnam(sys_get_temp_dir(), 'dcc_e9_');

function req(string $method, string $url, array $opts = []): array
{
    global $cookieJar;
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $opts['body'] ?? null,
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
}

function pass(string $label, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

function csrfFrom(string $html): ?string
{
    return preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

// 1-3 Auth / leads list
$r = req('GET', "{$base}/leads");
pass('GET /leads sem login → 302', $r['code'] === 302, (string) $r['code']);

$loginPage = req('GET', "{$base}/login");
$csrf = csrfFrom($loginPage['body']);
$r = req('POST', "{$base}/login", [
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    'body'    => http_build_query(['email' => $adminEmail, 'password' => $adminPass, '_csrf' => $csrf]),
]);
pass('Login admin', in_array($r['code'], [302, 200], true), (string) $r['code']);

$r = req('GET', "{$base}/leads");
pass('GET /leads logado com leads.view → 200', $r['code'] === 200, (string) $r['code']);

// API tests
$r = req('POST', "{$base}/api/leads/site", [
    'headers' => ['Content-Type: application/json'],
    'body'    => json_encode(['name' => 'Teste']),
]);
pass('POST /api/leads/site sem token → 403', $r['code'] === 403, (string) $r['code']);

$r = req('POST', "{$base}/api/leads/site", [
    'headers' => ['Content-Type: application/json', 'X-DCF-Lead-Token: token-invalido'],
    'body'    => json_encode(['name' => 'Teste']),
]);
pass('POST /api/leads/site token inválido → 403', $r['code'] === 403, (string) $r['code']);

$r = req('POST', "{$base}/api/leads/site", [
    'headers' => ['Content-Type: application/json', 'X-DCF-Lead-Token: ' . $token],
    'body'    => json_encode(['name' => 'Teste', 'website_url' => 'http://spam.bot']),
]);
pass('POST /api/leads/site honeypot → 201 silencioso', $r['code'] === 201 && str_contains($r['body'], '"success":true'), (string) $r['code']);

$r = req('POST', "{$base}/api/leads/site", [
    'headers' => ['Content-Type: application/json', 'X-DCF-Lead-Token: ' . $token],
    'body'    => json_encode(['email' => 'so-email@test.com']),
]);
pass('POST /api/leads/site payload inválido → 422', $r['code'] === 422, (string) $r['code']);

$payload = [
    'name' => 'Lead API JSON',
    'company_name' => 'Empresa WP',
    'email' => 'lead-json@example.com',
    'whatsapp' => '94999998888',
    'origin_page' => 'patrocinio/seja-patrocinador',
    'source_url' => 'https://dancacarajas.com.br/patrocinio/seja-patrocinador/',
    'contact_consent' => '1',
];
$r = req('POST', "{$base}/api/leads/site", [
    'headers' => ['Content-Type: application/json', 'X-DCF-Lead-Token: ' . $token],
    'body'    => json_encode($payload),
]);
$json = json_decode($r['body'], true);
pass('POST /api/leads/site JSON válido → 201', $r['code'] === 201 && ($json['lead_id'] ?? 0) > 0, (string) $r['code']);
$leadId = (int) ($json['lead_id'] ?? 0);

$r = req('POST', "{$base}/api/leads/site", [
    'headers' => ['Content-Type: application/x-www-form-urlencoded', 'X-DCF-Lead-Token: ' . $token],
    'body'    => http_build_query([
        'nome' => 'Lead Form URL',
        'empresa' => 'Patrocinador SA',
        'email' => 'lead-form@example.com',
        'origin_page' => 'patrocinio/fale-com-a-producao',
        'autorizacao_contato' => '1',
    ]),
]);
$json2 = json_decode($r['body'], true);
pass('POST /api/leads/site form-urlencoded válido → 201', $r['code'] === 201 && ($json2['lead_id'] ?? 0) > 0, (string) $r['code']);

// Internal CRUD
$r = req('GET', "{$base}/leads/create");
pass('GET /leads/create logado admin → 200', $r['code'] === 200, (string) $r['code']);
$csrfCreate = csrfFrom($r['body']);

$r = req('POST', "{$base}/leads", [
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    'body'    => http_build_query(['name' => '', '_csrf' => $csrfCreate]),
]);
pass('POST /leads sem nome → 422', $r['code'] === 422, (string) $r['code']);

$r = req('POST', "{$base}/leads", [
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    'body'    => http_build_query(['name' => 'Lead Manual', 'email' => 'invalido', '_csrf' => $csrfCreate]),
]);
pass('POST /leads e-mail inválido → 422', $r['code'] === 422, (string) $r['code']);

$r = req('POST', "{$base}/leads", [
    'headers' => ['Content-Type: application/x-www-form-urlencoded'],
    'body'    => http_build_query(['name' => 'Lead Manual Harness', 'email' => 'manual@example.com', 'status' => 'novo', '_csrf' => $csrfCreate]),
]);
pass('POST /leads válido manual → 302', $r['code'] === 302, (string) $r['code']);
preg_match('#Location: .*/leads/(\d+)#', $r['headers'], $mManual);
$manualId = (int) ($mManual[1] ?? 0);

$showId = $leadId > 0 ? $leadId : $manualId;
if ($showId > 0) {
    $r = req('GET', "{$base}/leads/{$showId}");
    pass("GET /leads/{$showId} → 200", $r['code'] === 200, (string) $r['code']);

    $r = req('GET', "{$base}/leads/{$showId}/edit");
    pass("GET /leads/{$showId}/edit → 200", $r['code'] === 200, (string) $r['code']);
    $csrfEdit = csrfFrom($r['body']);

    $r = req('POST', "{$base}/leads/{$showId}/update", [
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body'    => http_build_query(['name' => 'Lead Atualizado Harness', 'status' => 'em_triagem', '_csrf' => $csrfEdit]),
    ]);
    pass('POST /leads/{id}/update → 302', $r['code'] === 302, (string) $r['code']);

    $r = req('GET', "{$base}/leads/{$showId}/convert");
    pass("GET /leads/{$showId}/convert → 200", $r['code'] === 200, (string) $r['code']);
}

$r = req('GET', "{$base}/dashboard");
pass('Dashboard com cards leads → 200', $r['code'] === 200 && str_contains($r['body'], 'Leads'), (string) $r['code']);

$r = req('OPTIONS', "{$base}/api/leads/site", [
    'headers' => ['Origin: https://dancacarajas.com.br', 'Access-Control-Request-Method: POST'],
]);
pass('OPTIONS /api/leads/site CORS → 204', $r['code'] === 204 && str_contains($r['headers'], 'Access-Control-Allow-Origin'), (string) $r['code']);

$passed = count(array_filter($results, static fn ($x) => $x['ok']));
$total = count($results);
echo PHP_EOL . "Resumo: {$passed}/{$total} PASS" . PHP_EOL;
exit($passed === $total ? 0 : 1);
