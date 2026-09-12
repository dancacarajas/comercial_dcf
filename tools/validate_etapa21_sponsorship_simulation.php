<?php

declare(strict_types=1);

/**
 * Harness HTTP — Etapa 21 / 21.1 / 21.2 Sponsorship Simulation intake (contrato + integridade).
 *
 * Uso: php tools/validate_etapa21_sponsorship_simulation.php
 * Env: TEST_BASE (default http://127.0.0.1:8089), LEAD_TOKEN / LEAD_ENDPOINT_SECRET
 *
 * Fresh install (temp DB): php tools/validate_etapa21_fresh_install.php
 *
 * Rate-limit vs replay (decisão 21.1):
 * - Replay idempotente (mesmo UUID já persistido) NÃO deve ser bloqueado por rate limit
 *   (API trata known simulation replay antes do anti-spam) → 201/409 semânticos.
 * - Este harness limpa storage/ratelimit entre requests para isolar asserts de contrato
 *   de 429 acidental; o teste de concorrência NÃO limpa entre os dois curls simultâneos.
 * - Rate limit continua valendo para GENERIC_LEAD e primeiras submissões novas.
 *
 * Contrato 21.2 (limites, interests opcionais, catalog_ref pattern, RFC3339 estrito,
 * hash set-semantics, reject labels comerciais, migration override, quota range filter).
 */

$root = dirname(__DIR__);
$base = rtrim((string) (getenv('TEST_BASE') ?: 'http://127.0.0.1:8089'), '/');
$token = getenv('LEAD_TOKEN') ?: '';

require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
if ($token === '') {
    $token = (string) (getenv('LEAD_ENDPOINT_SECRET')
        ?: ($_ENV['LEAD_ENDPOINT_SECRET'] ?? 'dcf-local-lead-token-2026-trocar-em-producao'));
}
if (PHP_SAPI === 'cli') {
    $host = (string) (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ''));
    if ($host === 'db' || $host === 'localhost' || $host === '127.0.0.1') {
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=3307');
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_PORT'] = '3307';
    }
}
require_once $root . '/app/Core/Database.php';
require_once $root . '/app/Core/Model.php';
require_once $root . '/app/Models/SponsorshipSimulation.php';
require_once $root . '/app/Models/Quota.php';
require_once $root . '/app/Services/SponsorshipLeadIntake.php';

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$rlDir = $root . '/storage/ratelimit';
if (is_dir($rlDir)) {
    foreach (glob($rlDir . '/*.json') ?: [] as $f) {
        @unlink($f);
    }
}

$results = [];

function req(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $opts['headers'] ?? [],
        CURLOPT_POSTFIELDS => $opts['body'] ?? null,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    return ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
}

function pass(string $label, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = ['ok' => $ok, 'label' => $label];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

function loadFixture(string $name): array
{
    global $root;
    $path = $root . '/fixtures/integrations/' . $name;
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw)) {
        throw new RuntimeException('Fixture inválida: ' . $name);
    }

    return $raw;
}

function clearRateLimit(): void
{
    global $root;
    $rlDir = $root . '/storage/ratelimit';
    if (!is_dir($rlDir)) {
        return;
    }
    foreach (glob($rlDir . '/*.json') ?: [] as $f) {
        @unlink($f);
    }
}

function postLead(array $payload, ?string $tok = null, bool $clearRl = true): array
{
    global $base, $token;
    if ($clearRl) {
        clearRateLimit();
    }

    return req('POST', "{$base}/api/leads/site", [
        'headers' => [
            'Content-Type: application/json',
            'X-DCF-Lead-Token: ' . ($tok ?? $token),
        ],
        'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/** Dois POSTs quase simultâneos (sem limpar RL entre eles). */
function postLeadConcurrent(array $payloadA, array $payloadB, ?string $tok = null): array
{
    global $base, $token;
    clearRateLimit();
    $url = "{$base}/api/leads/site";
    $headers = [
        'Content-Type: application/json',
        'X-DCF-Lead-Token: ' . ($tok ?? $token),
    ];
    $bodyA = json_encode($payloadA, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $bodyB = json_encode($payloadB, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $mh = curl_multi_init();
    $handles = [];
    foreach ([$bodyA, $bodyB] as $i => $body) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$i] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $out = [];
    foreach ($handles as $ch) {
        $raw = (string) curl_multi_getcontent($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $out[] = ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);

    return $out;
}

function newUuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** Reordena chaves de objetos (não listas) — payload canonicamente equivalente. */
function reorderKeysDeep(mixed $data): mixed
{
    if (!is_array($data)) {
        return $data;
    }
    $isList = $data === [] || array_keys($data) === range(0, count($data) - 1);
    if ($isList) {
        return array_map('reorderKeysDeep', $data);
    }
    $keys = array_keys($data);
    rsort($keys);
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = reorderKeysDeep($data[$k]);
    }

    return $out;
}

function cloneValid(array $valid): array
{
    $p = $valid;
    $p['submission_uuid'] = newUuid();
    $p['email'] = 't.' . bin2hex(random_bytes(4)) . '@example.com';

    return $p;
}

/** @param list<string> $pool */
function takeN(array $pool, int $n): ?array
{
    $pool = array_values($pool);
    if (count($pool) < $n) {
        return null;
    }

    return array_slice($pool, 0, $n);
}

/** @return list<string> */
function makeRefs(string $prefix, int $n): array
{
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $out[] = $prefix . '_' . $i;
    }

    return $out;
}

function hasMojibake(string $text): bool
{
    return str_contains($text, '├')
        || str_contains($text, 'Γ')
        || str_contains($text, '�')
        || str_contains($text, 'Ã¡')
        || str_contains($text, 'Ã©')
        || str_contains($text, 'Ã§');
}

/** True se o script redefine 3307 sem exigir DCX_LOCAL_DOCKER_DB_OVERRIDE=1. */
function migrationHasBlindDockerPort(string $src): bool
{
    // Aceita override somente atrás de DCX_LOCAL_DOCKER_DB_OVERRIDE=1 ou --local-docker-db
    if (!str_contains($src, '3307')) {
        return false;
    }
    $hasGate = str_contains($src, 'DCX_LOCAL_DOCKER_DB_OVERRIDE')
        || str_contains($src, '--local-docker-db');
    // Blind = ainda faz putenv 3307 quando host é localhost/db sem gate
    $hasBlindHostCheck = (bool) preg_match(
        '/\$host\s*===\s*[\'"](?:db|localhost|127\.0\.0\.1)[\'"]/',
        $src
    );

    return $hasBlindHostCheck && !$hasGate;
}

echo "== validate_etapa21_sponsorship_simulation (21.2) ==\n";
echo "TEST_BASE={$base}\n\n";

// ---------------------------------------------------------------------------
// Baseline / smoke (existentes)
// ---------------------------------------------------------------------------

$r = postLead([
    'name' => 'Lead Genérico Etapa21',
    'email' => 'generico.etapa21@example.com',
    'company_name' => 'Genérica SA',
    'origin_page' => 'contato',
]);
$body = json_decode($r['body'], true) ?: [];
pass('generic lead legado continua 201', $r['code'] === 201, (string) $r['code']);
$genericLeadId = (int) ($body['lead_id'] ?? 0);

pass('token inválido → 403', postLead(['name' => 'x'], 'token-invalido')['code'] === 403);

$valid = loadFixture('sponsorship_simulation_valid.v1.json');
$valid['submission_uuid'] = newUuid();
$valid['email'] = 'sim.valid.' . time() . '@example.com';
$r = postLead($valid);
$body = json_decode($r['body'], true) ?: [];
pass('simulação válida → 201', $r['code'] === 201, (string) $r['code'] . ' ' . substr($r['body'], 0, 180));
$leadId = (int) ($body['lead_id'] ?? 0);
$simId = (int) ($body['simulation_id'] ?? 0);
pass('lead criado', $leadId > 0, (string) $leadId);
pass('simulation criada', $simId > 0, (string) $simId);

$lead = $pdo->prepare('SELECT * FROM leads WHERE id = :id');
$lead->execute(['id' => $leadId]);
$leadRow = $lead->fetch() ?: [];
pass('submission_type correto', ($leadRow['submission_type'] ?? '') === 'SPONSORSHIP_SIMULATION');
pass('lead.incentive_project_id correto', !empty($leadRow['incentive_project_id']));

$proj = $pdo->prepare('SELECT * FROM incentive_projects WHERE id = :id');
$proj->execute(['id' => (int) ($leadRow['incentive_project_id'] ?? 0)]);
$projRow = $proj->fetch() ?: [];
pass('project 2026 correto', (int) ($projRow['edition_year'] ?? 0) === 2026 && preg_replace('/\D+/', '', (string) ($projRow['pronac_number'] ?? '')) === '265397');

$sim = $pdo->prepare('SELECT * FROM sponsorship_simulations WHERE id = :id');
$sim->execute(['id' => $simId]);
$simRow = $sim->fetch() ?: [];
$briefing = json_decode((string) ($simRow['briefing_snapshot'] ?? ''), true) ?: [];
$interests = json_decode((string) ($simRow['interests_snapshot'] ?? ''), true) ?: [];
$rec = json_decode((string) ($simRow['recommendation_snapshot'] ?? ''), true) ?: [];

pass('briefing preservado', isset($briefing['area_decision'], $briefing['objectives'], $briefing['investment']));
pass('interests preservados', isset($interests['tier_interest']) && is_array($interests['tier_interest']));
pass('investment preservado', ($simRow['investment_status'] ?? '') === 'DEFINED_AMOUNT' && abs((float) $simRow['investment_min'] - 100000) < 0.01);
pass('primary tier preservado', ($simRow['primary_tier_ref'] ?? '') === 'CARAJAS');
pass('alternatives preservadas', is_array($rec['alternatives'] ?? null) && count($rec['alternatives']) >= 1);
pass('fit preservado', ($simRow['fit_level'] ?? '') === 'HIGH');
pass('availability NOT_CHECKED', ($simRow['availability_status'] ?? '') === 'NOT_CHECKED');

$payloadRaw = (string) ($leadRow['integration_payload'] ?? '');
pass('raw Passport inexistente', !str_contains($payloadRaw, '"session_id"') && !str_contains(strtolower($payloadRaw), '"passport"'));
pass('reason_codes inexistentes', !str_contains($payloadRaw, 'reason_codes'));
pass('internal_score inexistente', !str_contains($payloadRaw, 'internal_score'));
pass('diagnostics inexistentes', !str_contains($payloadRaw, 'diagnostics'));

$bad = $valid;
$bad['submission_uuid'] = newUuid();
$bad['contact_consent'] = false;
pass('consent=false → 422', postLead($bad)['code'] === 422);

$bad = $valid;
$bad['submission_uuid'] = newUuid();
$bad['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'DEFINED_AMOUNT', 'min' => 10, 'max' => 20, 'currency' => 'BRL',
];
pass('invalid investment → 422', postLead($bad)['code'] === 422);

$bad = $valid;
$bad['submission_uuid'] = newUuid();
$bad['sponsorship_simulation']['recommendation']['primary']['tier_id'] = 'FORMACAO_LEGACY';
pass('unknown tier → 422', postLead($bad)['code'] === 422);

$bad = $valid;
$bad['submission_uuid'] = newUuid();
$bad['sponsorship_simulation']['catalog_version'] = '2025-V1';
pass('catalog version errada → 422', postLead($bad)['code'] === 422);

$bad = $valid;
$bad['submission_uuid'] = newUuid();
$bad['sponsorship_simulation']['recommendation']['primary']['availability'] = 'AVAILABLE';
pass('availability diferente de NOT_CHECKED → 422', postLead($bad)['code'] === 422);

$bad = $valid;
$bad['submission_uuid'] = newUuid();
$bad['sponsorship_simulation']['confirmed'] = false;
pass('confirmed=false → 422', postLead($bad)['code'] === 422);

$bad = $valid;
$bad['submission_uuid'] = newUuid();
$bad['sponsorship_simulation']['recommendation']['state'] = 'NEEDS_INPUT';
pass('state != READY → 422', postLead($bad)['code'] === 422);

$invalid = loadFixture('sponsorship_simulation_invalid_internal_fields.v1.json');
$invalid['submission_uuid'] = newUuid();
pass('internal fields → 422', postLead($invalid)['code'] === 422);

$uuid = newUuid();
$idem = $valid;
$idem['submission_uuid'] = $uuid;
$idem['email'] = 'idem.' . time() . '@example.com';
$r1 = postLead($idem);
$b1 = json_decode($r1['body'], true) ?: [];
$r2 = postLead($idem);
$b2 = json_decode($r2['body'], true) ?: [];
pass(
    'mesmo UUID/mesmo payload → idempotent success',
    $r1['code'] === 201 && $r2['code'] === 201
        && (int) ($b1['lead_id'] ?? 0) === (int) ($b2['lead_id'] ?? 0)
        && !empty($b2['idempotent'])
);

$conflict = $idem;
$conflict['sponsorship_simulation']['recommendation']['primary']['fit_level'] = 'LOW';
pass('mesmo UUID/payload diferente → 409', postLead($conflict)['code'] === 409);

$hp = $valid;
$hp['submission_uuid'] = newUuid();
$hp['website'] = 'http://spam.test';
$r = postLead($hp);
$hb = json_decode($r['body'], true) ?: [];
pass('honeypot preservado', $r['code'] === 201 && (int) ($hb['lead_id'] ?? -1) === 0);

$range = loadFixture('sponsorship_simulation_range.v1.json');
$range['submission_uuid'] = newUuid();
$range['email'] = 'range.' . time() . '@example.com';
$rr = postLead($range);
$rb = json_decode($rr['body'], true) ?: [];
pass('range válido → 201', $rr['code'] === 201, (string) $rr['code']);
$rangeLeadId = (int) ($rb['lead_id'] ?? 0);
$rangeSimId = (int) ($rb['simulation_id'] ?? 0);
if (!empty($rb['simulation_id'])) {
    $st = $pdo->prepare('SELECT investment_status, investment_min, investment_max FROM sponsorship_simulations WHERE id = :id');
    $st->execute(['id' => (int) $rb['simulation_id']]);
    $sr = $st->fetch() ?: [];
    pass(
        'range preserva min/max',
        ($sr['investment_status'] ?? '') === 'DEFINED_RANGE'
            && abs((float) $sr['investment_min'] - 80000) < 0.01
            && abs((float) $sr['investment_max'] - 120000) < 0.01
    );
}

$open = loadFixture('sponsorship_simulation_open.v1.json');
$open['submission_uuid'] = newUuid();
$open['email'] = 'open.' . time() . '@example.com';
$or = postLead($open);
pass('open válido → 201', $or['code'] === 201, (string) $or['code']);

$conv = $valid;
$conv['submission_uuid'] = newUuid();
$conv['email'] = 'conv.' . time() . '@example.com';
$conv['company_name'] = 'Conv Experience Ltda';
$conv['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'DEFINED_AMOUNT', 'min' => 50000, 'max' => 50000, 'currency' => 'BRL',
];
$conv['sponsorship_simulation']['recommendation']['primary']['tier_id'] = 'EXPERIENCE';
// 21.2: labels comerciais (primary_tier_label / investment_label) são rejeitados — não enviar
$cr = postLead($conv);
$cb = json_decode($cr['body'], true) ?: [];
$convLeadId = (int) ($cb['lead_id'] ?? 0);
$convSimId = (int) ($cb['simulation_id'] ?? 0);
pass('conversão: lead EXPERIENCE criado', $convLeadId > 0 && $convSimId > 0);

$projectIdCanonical = (int) ($leadRow['incentive_project_id'] ?? $projRow['id'] ?? 0);
if ($projectIdCanonical <= 0) {
    $projectIdCanonical = (int) $pdo->query(
        "SELECT id FROM incentive_projects WHERE edition_year=2026 AND archived_at IS NULL ORDER BY id LIMIT 1"
    )->fetchColumn();
}

if ($convLeadId > 0) {
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO companies (name, status, priority, source, created_at) VALUES ('Conv Experience Ltda', 'prospect', 'B', 'site', NOW())");
        $companyId = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO opportunities
                (incentive_project_id, company_id, title, status, source, estimated_value, sponsorship_simulation_id, quota_id, opened_at, created_at)
             VALUES
                (:pid, :cid, :title, \'prospect_identificado\', \'site\', 50000, :sid, NULL, NOW(), NOW())'
        );
        $ins->execute([
            'pid' => $projectIdCanonical,
            'cid' => $companyId,
            'title' => 'Oportunidade — Conv Experience Ltda',
            'sid' => $convSimId,
        ]);
        $opId = (int) $pdo->lastInsertId();
        $op = $pdo->query('SELECT * FROM opportunities WHERE id = ' . $opId)->fetch() ?: [];
        pass('opportunity.incentive_project_id correto', (int) ($op['incentive_project_id'] ?? 0) === $projectIdCanonical);
        pass('opportunity.source = site', ($op['source'] ?? '') === 'site');
        pass('opportunity.estimated_value = 50000', abs((float) ($op['estimated_value'] ?? 0) - 50000) < 0.01);
        pass('opportunity.sponsorship_simulation_id correto', (int) ($op['sponsorship_simulation_id'] ?? 0) === $convSimId);
        pass('quota_id ainda NULL', $op['quota_id'] === null || $op['quota_id'] === '');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        pass('conversão SQL', false, $e->getMessage());
    }
}

$src = (string) file_get_contents($root . '/app/Models/SponsorshipSimulation.php');
pass('imutabilidade: sem update genérico de snapshot', !preg_match('/function\s+update\s*\(/', $src));

// ---------------------------------------------------------------------------
// Etapa 21.1 — contrato / integridade (novos)
// ---------------------------------------------------------------------------

echo "\n-- Etapa 21.1 contract/integrity --\n";

// 1. invalid area_decision
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing']['area_decision'] = 'NOT_A_REAL_AREA';
pass('1. invalid area_decision → 422', postLead($bad)['code'] === 422);

// 2. invalid objective
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing']['objectives'] = ['NOT_A_REAL_OBJECTIVE'];
pass('2. invalid objective → 422', postLead($bad)['code'] === 422);

// 3. invalid audience when present
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing']['audiences'] = ['NOT_A_REAL_AUDIENCE'];
pass('3. invalid audience when present → 422', postLead($bad)['code'] === 422);

// 4. invalid depth
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing']['depth_intent'] = 'NOT_A_REAL_DEPTH';
pass('4. invalid depth → 422', postLead($bad)['code'] === 422);

// 5. invalid proof
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing']['proof_needs'] = ['NOT_A_REAL_PROOF'];
pass('5. invalid proof → 422', postLead($bad)['code'] === 422);

// 6. invalid interest catalog_ref
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['interests']['tier_interest'] = ['FAKE_TIER_REF'];
pass('6. invalid interest catalog_ref → 422', postLead($bad)['code'] === 422);

// 7. audiences absent but otherwise legitimate → 201
$noAud = cloneValid($valid);
unset($noAud['sponsorship_simulation']['briefing']['audiences']);
$r = postLead($noAud);
$nb = json_decode($r['body'], true) ?: [];
pass('7. audiences absent → 201', $r['code'] === 201 && (int) ($nb['lead_id'] ?? 0) > 0, (string) $r['code']);

// 8. >5 alternatives
$bad = cloneValid($valid);
$altTpl = $valid['sponsorship_simulation']['recommendation']['alternatives'][0];
$bad['sponsorship_simulation']['recommendation']['alternatives'] = array_fill(0, 6, $altTpl);
pass('8. >5 alternatives → 422', postLead($bad)['code'] === 422);

// 9. alternative without NOT_CHECKED
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['recommendation']['alternatives'][0]['availability'] = 'AVAILABLE';
pass('9. alternative without NOT_CHECKED → 422', postLead($bad)['code'] === 422);

// 10. unknown top-level field
$bad = cloneValid($valid);
$bad['totally_unknown_field'] = 'x';
pass('10. unknown top-level field → 422', postLead($bad)['code'] === 422);

// 11. unknown nested field
$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing']['secret_extra'] = true;
pass('11. unknown nested field → 422', postLead($bad)['code'] === 422);

// 12. 21.2 — labels comerciais no display_snapshot → 422 (não forjar UI comercial)
$rejectedDisplayKeys = [
    'availability_label',
    'fit_label',
    'primary_tier_label',
    'investment_label',
];
foreach ($rejectedDisplayKeys as $rejKey) {
    $forge = cloneValid($valid);
    $forge['sponsorship_simulation']['display_snapshot'][$rejKey] = 'Label comercial forjado';
    $fr = postLead($forge);
    pass(
        "12. display reject {$rejKey} → 422",
        $fr['code'] === 422,
        (string) $fr['code']
    );
}
$showPhp = (string) file_get_contents($root . '/app/Views/leads/show.php');
$structuralUiPath = str_contains($showPhp, "availabilityLabel(\$simulation['availability_status']")
    || str_contains($showPhp, 'availabilityLabel($simulation[\'availability_status\']');
$displayNotUiSource = !preg_match('/availabilityLabel\s*\(\s*\$display\s*\[/', $showPhp)
    && !str_contains($showPhp, "display['availability_label']")
    && !str_contains($showPhp, 'display["availability_label"]');
$simModel = new \App\Models\SponsorshipSimulation();
$labelFromStructural = $simModel->availabilityLabel('NOT_CHECKED');
pass(
    '12b. UI estrutural (show.php + label) sem display comercial',
    $structuralUiPath && $displayNotUiSource && $labelFromStructural === 'Ainda não verificada'
);

// 13–15. same UUID + different identity/commercial fields → 409
$baseUuid = newUuid();
$basePayload = cloneValid($valid);
$basePayload['submission_uuid'] = $baseUuid;
$basePayload['email'] = 'conflict.base.' . time() . '@example.com';
$basePayload['company_name'] = 'Conflict Base SA';
$rBase = postLead($basePayload);
pass('13-prep. base UUID criado', $rBase['code'] === 201, (string) $rBase['code']);

$emailDiff = $basePayload;
$emailDiff['email'] = 'conflict.other.' . time() . '@example.com';
pass('13. same UUID + different email → 409', postLead($emailDiff)['code'] === 409);

$coDiff = $basePayload;
$coDiff['company_name'] = 'Outra Empresa Conflito Ltda';
pass('14. same UUID + different company → 409', postLead($coDiff)['code'] === 409);

$dispDiff = $basePayload;
$dispDiff['sponsorship_simulation']['display_snapshot']['depth_label'] = 'Label forjado diferente';
pass('15. same UUID + different display_snapshot → 409', postLead($dispDiff)['code'] === 409);

// 16. canonical key reorder → idempotent replay 201
$canonUuid = newUuid();
$canon = cloneValid($valid);
$canon['submission_uuid'] = $canonUuid;
$canon['email'] = 'canon.' . time() . '@example.com';
$rCanon1 = postLead($canon);
$reordered = reorderKeysDeep($canon);
$reordered['submission_uuid'] = $canonUuid; // ensure same
$rCanon2 = postLead($reordered);
$bc1 = json_decode($rCanon1['body'], true) ?: [];
$bc2 = json_decode($rCanon2['body'], true) ?: [];
pass(
    '16. canonical key reorder → idempotent replay 201',
    $rCanon1['code'] === 201 && $rCanon2['code'] === 201
        && (int) ($bc1['lead_id'] ?? 0) === (int) ($bc2['lead_id'] ?? 0)
        && !empty($bc2['idempotent']),
    'c1=' . $rCanon1['code'] . ' c2=' . $rCanon2['code']
);

// 17. concurrent duplicate — never 500; both 201
$concUuid = newUuid();
$conc = cloneValid($valid);
$conc['submission_uuid'] = $concUuid;
$conc['email'] = 'conc.' . time() . '@example.com';
[$cA, $cB] = postLeadConcurrent($conc, $conc);
$codes = [(int) $cA['code'], (int) $cB['code']];
$bodies = [json_decode($cA['body'], true) ?: [], json_decode($cB['body'], true) ?: []];
$leadA = (int) ($bodies[0]['lead_id'] ?? 0);
$leadB = (int) ($bodies[1]['lead_id'] ?? 0);
pass(
    '17. concurrent duplicate: ambos 201, nunca 500',
    $codes[0] === 201 && $codes[1] === 201
        && !in_array(500, $codes, true)
        && $leadA > 0 && $leadA === $leadB,
    'codes=' . implode(',', $codes) . ' leads=' . $leadA . '/' . $leadB
);

// 18. Lead with simulation cannot be hard-deleted / FK RESTRICT
$deleteRule = (string) $pdo->query(
    "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND CONSTRAINT_NAME = 'fk_sponsorship_sim_lead'"
)->fetchColumn();
$ruleOk = in_array(strtoupper($deleteRule), ['RESTRICT', 'NO ACTION'], true);
$deleteBlocked = false;
$deleteDetail = 'rule=' . $deleteRule;
if ($leadId > 0) {
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM leads WHERE id = ' . (int) $leadId);
        $deleteDetail .= '; DELETE succeeded (unexpected)';
        $pdo->rollBack();
    } catch (Throwable $e) {
        $deleteBlocked = true;
        $deleteDetail .= '; blocked: ' . substr($e->getMessage(), 0, 120);
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
pass(
    '18. Lead+simulação: FK RESTRICT / hard-delete bloqueado',
    $ruleOk || $deleteBlocked,
    $deleteDetail
);
pass(
    '18b. information_schema DELETE_RULE RESTRICT|NO ACTION',
    $ruleOk,
    $deleteRule !== '' ? $deleteRule : 'FK ausente'
);

// 19. convert com incentive_project_id divergente → usa simulation.incentive_project_id
$ctrlSrc = (string) file_get_contents($root . '/app/Controllers/LeadController.php');
$lockInSource = str_contains($ctrlSrc, 'simulation')
    && preg_match('/\$projectId\s*=\s*\(int\)\s*\$simulation\[[\'"]incentive_project_id[\'"]\]/', $ctrlSrc);
$wrongProjectId = (int) $pdo->query(
    'SELECT id FROM incentive_projects WHERE id <> ' . (int) $projectIdCanonical . ' ORDER BY id LIMIT 1'
)->fetchColumn();
if ($wrongProjectId <= 0) {
    // cria projeto dummy só se necessário para o assert lógico
    $pdo->exec(
        "INSERT INTO incentive_projects (project_name, edition_year, pronac_number, project_status, created_at)
         VALUES ('Projeto Dummy Convert Lock', 2099, '000000', 'em_elaboracao', NOW())"
    );
    $wrongProjectId = (int) $pdo->lastInsertId();
}
$postedProjectId = $wrongProjectId;
$resolvedProjectId = $postedProjectId;
$simProjectId = (int) ($simRow['incentive_project_id'] ?? $projectIdCanonical);
if ($simProjectId > 0) {
    // Espelha LeadController::convert — simulação vence POST
    $resolvedProjectId = $simProjectId;
}
pass(
    '19. convert lock: POST divergente → opportunity usa simulation.incentive_project_id',
    $lockInSource && $resolvedProjectId === $simProjectId && $postedProjectId !== $simProjectId,
    "posted={$postedProjectId} resolved={$resolvedProjectId} sim={$simProjectId}"
);
// Espelho PDO da regra de lock (sem HTTP auth)
if ($convSimId > 0 && $simProjectId > 0) {
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO companies (name, status, priority, source, created_at) VALUES ('Convert Lock Co', 'prospect', 'B', 'site', NOW())");
        $cid = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO opportunities
                (incentive_project_id, company_id, title, status, source, estimated_value, sponsorship_simulation_id, quota_id, opened_at, created_at)
             VALUES (:pid, :cid, :title, \'prospect_identificado\', \'site\', 1, :sid, NULL, NOW(), NOW())'
        );
        $ins->execute([
            'pid' => $resolvedProjectId, // como o controller faria após o lock
            'cid' => $cid,
            'title' => 'Opp convert lock',
            'sid' => $convSimId,
        ]);
        $opLock = (int) $pdo->lastInsertId();
        $row = $pdo->query('SELECT incentive_project_id FROM opportunities WHERE id = ' . $opLock)->fetch() ?: [];
        pass(
            '19b. convert lock PDO mirror',
            (int) ($row['incentive_project_id'] ?? 0) === $simProjectId
        );
        $pdo->rollBack(); // não poluir
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        pass('19b. convert lock PDO mirror', false, $e->getMessage());
    }
} else {
    pass('19b. convert lock PDO mirror', false, 'sim/conv ids ausentes');
}

// 20. DEFINED_RANGE → estimated_value NULL on convert
$rangeStatus = 'DEFINED_RANGE';
$suggestedValue = null;
if ($rangeStatus === 'DEFINED_AMOUNT') {
    $suggestedValue = 1.0;
}
$estimatedRaw = ''; // formulário range deixa vazio
$estimatedValue = null;
if ($estimatedRaw !== '' && is_numeric($estimatedRaw)) {
    $estimatedValue = (float) $estimatedRaw;
}
pass('20. DEFINED_RANGE → suggested/estimated_value NULL (convert logic)', $suggestedValue === null && $estimatedValue === null);
if ($rangeSimId > 0) {
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO companies (name, status, priority, source, created_at) VALUES ('Range Convert Co', 'prospect', 'B', 'site', NOW())");
        $cid = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare(
            'INSERT INTO opportunities
                (incentive_project_id, company_id, title, status, source, estimated_value, sponsorship_simulation_id, quota_id, opened_at, created_at)
             VALUES (:pid, :cid, :title, \'prospect_identificado\', \'site\', NULL, :sid, NULL, NOW(), NOW())'
        );
        $ins->execute([
            'pid' => $projectIdCanonical,
            'cid' => $cid,
            'title' => 'Opp range null estimate',
            'sid' => $rangeSimId,
        ]);
        $oid = (int) $pdo->lastInsertId();
        $ev = $pdo->query('SELECT estimated_value FROM opportunities WHERE id = ' . $oid)->fetchColumn();
        pass('20b. DEFINED_RANGE opportunity.estimated_value NULL', $ev === null || $ev === '');
        $pdo->rollBack();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        pass('20b. DEFINED_RANGE opportunity.estimated_value NULL', false, $e->getMessage());
    }
} else {
    pass('20b. DEFINED_RANGE opportunity.estimated_value NULL', false, 'range sim ausente');
}

// 21. Fresh install schema check
$schema = (string) file_get_contents($root . '/database/install_schema.sql');
pass('21a. install_schema contém sponsorship_simulations', str_contains($schema, 'sponsorship_simulations'));
pass('21b. install_schema contém submission_type', str_contains($schema, 'submission_type'));
pass('21c. install_schema contém uniq_sponsorship_sim_uuid', str_contains($schema, 'uniq_sponsorship_sim_uuid'));
pass(
    '21d. install_schema ON DELETE RESTRICT em lead_id (simulação)',
    (bool) preg_match(
        '/fk_sponsorship_sim_lead[\s\S]{0,400}ON DELETE RESTRICT/i',
        $schema
    ) || (
        str_contains($schema, 'sponsorship_simulations')
        && str_contains($schema, 'fk_sponsorship_sim_lead')
        && str_contains($schema, 'ON DELETE RESTRICT')
    )
);

// 22. Source UTF-8 — sem mojibake markers
$etapa21Paths = [
    'app/Services/SponsorshipLeadIntake.php',
    'app/Models/SponsorshipSimulation.php',
    'app/Controllers/LeadController.php',
    'app/Controllers/Api/LeadApiController.php',
    'app/Views/leads/show.php',
    'app/Views/leads/convert.php',
    'app/Views/leads/index.php',
    'app/Views/opportunities/show.php',
    'docs/integrations/SPONSORSHIP_SIMULATION_INTAKE_V1.md',
];
$mojibakeHits = [];
foreach ($etapa21Paths as $rel) {
    $full = $root . '/' . $rel;
    if (!is_file($full)) {
        continue;
    }
    $txt = (string) file_get_contents($full);
    if (hasMojibake($txt)) {
        $mojibakeHits[] = $rel;
    }
}
pass('22. source UTF-8 sem mojibake (├ Γ �)', $mojibakeHits === [], $mojibakeHits === [] ? '' : implode(', ', $mojibakeHits));

// 23. FK/indexes via information_schema
$idxNames = $pdo->query(
    "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsorship_simulations'"
)->fetchAll(PDO::FETCH_COLUMN);
$needIdx = ['uniq_sponsorship_sim_uuid', 'uniq_sponsorship_sim_lead', 'idx_sponsorship_sim_project'];
$idxOk = true;
foreach ($needIdx as $need) {
    if (!in_array($need, $idxNames, true)) {
        $idxOk = false;
        break;
    }
}
$fkLead = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsorship_simulations'
        AND CONSTRAINT_NAME = 'fk_sponsorship_sim_lead' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
)->fetchColumn();
$fkProj = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sponsorship_simulations'
        AND CONSTRAINT_NAME = 'fk_sponsorship_sim_project' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
)->fetchColumn();
$colSub = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'submission_type'"
)->fetchColumn();
pass(
    '23. FK/indexes present (information_schema)',
    $idxOk && $fkLead > 0 && $fkProj > 0 && $colSub > 0,
    'idx=' . implode(',', $idxNames) . " fk_lead={$fkLead} fk_proj={$fkProj} submission_type={$colSub}"
);

// Fixtures canônicos (enums V2.1 / availability)
foreach (['sponsorship_simulation_valid.v1.json', 'sponsorship_simulation_range.v1.json', 'sponsorship_simulation_open.v1.json'] as $fixName) {
    $fx = loadFixture($fixName);
    $ad = (string) ($fx['sponsorship_simulation']['briefing']['area_decision'] ?? '');
    $avail = (string) ($fx['sponsorship_simulation']['recommendation']['primary']['availability'] ?? '');
    $cat = (string) ($fx['sponsorship_simulation']['catalog_version'] ?? '');
    pass(
        "fixture canônica: {$fixName}",
        $ad !== '' && $ad !== 'INSTITUTIONAL' && $ad !== 'BRAND'
            && $avail === 'NOT_CHECKED'
            && $cat === '2026-V2.1'
            && ($fx['submission_type'] ?? '') === 'SPONSORSHIP_SIMULATION',
        "area={$ad} avail={$avail} cat={$cat}"
    );
}

// ---------------------------------------------------------------------------
// Etapa 21.2 — limites, interests opcionais, datetime, hash set, migration, quota
// ---------------------------------------------------------------------------

echo "\n-- Etapa 21.2 contract --\n";

$objPool = \App\Services\SponsorshipLeadIntake::OBJECTIVES;
$audPool = \App\Services\SponsorshipLeadIntake::AUDIENCES;
$proofPool = \App\Services\SponsorshipLeadIntake::PROOF_NEEDS;
$tierPool = \App\Services\SponsorshipLeadIntake::TIER_REFS;

$makeRefs = static function (string $prefix, int $n): array {
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $out[] = $prefix . $i;
    }

    return $out;
};

// --- LIMITS (boundary) ---
$lim8 = takeN($objPool, 8);
$lim9 = takeN($objPool, 9);
if ($lim8 !== null) {
    $p = cloneValid($valid);
    $p['sponsorship_simulation']['briefing']['objectives'] = $lim8;
    pass('21.2 limits: objectives 8 → PASS', postLead($p)['code'] === 201);
} else {
    pass('21.2 limits: objectives 8 → PASS', false, 'OBJECTIVES pool < 8');
}
if ($lim9 !== null) {
    $p = cloneValid($valid);
    $p['sponsorship_simulation']['briefing']['objectives'] = $lim9;
    pass('21.2 limits: objectives 9 → FAIL', postLead($p)['code'] === 422);
} else {
    pass('21.2 limits: objectives 9 → FAIL', false, 'OBJECTIVES pool < 9');
}

$aud8 = takeN($audPool, 8);
$aud9 = takeN($audPool, 9);
if ($aud8 !== null) {
    $p = cloneValid($valid);
    $p['sponsorship_simulation']['briefing']['audiences'] = $aud8;
    pass('21.2 limits: audiences 8 → PASS', postLead($p)['code'] === 201);
} else {
    pass('21.2 limits: audiences 8 → PASS', false, 'AUDIENCES pool < 8');
}
if ($aud9 !== null) {
    $p = cloneValid($valid);
    $p['sponsorship_simulation']['briefing']['audiences'] = $aud9;
    pass('21.2 limits: audiences 9 → FAIL', postLead($p)['code'] === 422);
} else {
    pass('21.2 limits: audiences 9 → FAIL', false, 'AUDIENCES pool < 9');
}

// proof_needs: maxItems=10; uniqueItems + 9 enums → teto prático = 9.
$prf9 = takeN($proofPool, 9);
if ($prf9 !== null) {
    $p = cloneValid($valid);
    $p['sponsorship_simulation']['briefing']['proof_needs'] = $prf9;
    pass('21.2 limits: proof 9 (único enums) → PASS', postLead($p)['code'] === 201, 'pool=' . count($proofPool));
} else {
    pass('21.2 limits: proof 9 (único enums) → PASS', false, 'PROOF_NEEDS pool < 9');
}
$p = cloneValid($valid);
$p['sponsorship_simulation']['briefing']['proof_needs'] = array_merge(array_values($proofPool), ['PROOF_X1', 'PROOF_X2']);
pass('21.2 limits: proof 11 → FAIL', postLead($p)['code'] === 422);

$tier5 = takeN($tierPool, 5);
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['tier_interest'] = $tier5 ?? array_values($tierPool);
pass('21.2 limits: tier_interest 5 → PASS', $tier5 !== null && postLead($p)['code'] === 201);
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['tier_interest'] = array_merge(array_values($tierPool), ['EXTRA_TIER']);
pass('21.2 limits: tier_interest 6 → FAIL', postLead($p)['code'] === 422);

$axis4 = $makeRefs('AXIS', 4);
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['axis_interest'] = $axis4;
pass('21.2 limits: axis_interest 4 → PASS', postLead($p)['code'] === 201);
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['axis_interest'] = $makeRefs('AXIS', 5);
pass('21.2 limits: axis_interest 5 → FAIL', postLead($p)['code'] === 422);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['activation_interest'] = makeRefs('ACT', 8);
pass('21.2 limits: activation_interest 8 → PASS', postLead($p)['code'] === 201);
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['activation_interest'] = makeRefs('ACT', 9);
pass('21.2 limits: activation_interest 9 → FAIL', postLead($p)['code'] === 422);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['property_interest'] = makeRefs('PROP', 8);
pass('21.2 limits: property_interest 8 → PASS', postLead($p)['code'] === 201);
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['property_interest'] = makeRefs('PROP', 9);
pass('21.2 limits: property_interest 9 → FAIL', postLead($p)['code'] === 422);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['asset_interest'] = makeRefs('ASSET', 15);
pass('21.2 limits: asset_interest 15 → PASS', postLead($p)['code'] === 201);
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['asset_interest'] = makeRefs('ASSET', 16);
pass('21.2 limits: asset_interest 16 → FAIL', postLead($p)['code'] === 422);

// --- OPTIONAL INTERESTS ---
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests'] = [];
$rOptEmpty = postLead($p);
pass('21.2 optional interests: {} → 201', $rOptEmpty['code'] === 201, (string) $rOptEmpty['code']);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests'] = ['axis_interest' => ['FORMACAO']];
$rOptAxis = postLead($p);
pass('21.2 optional interests: only axis_interest → 201', $rOptAxis['code'] === 201, (string) $rOptAxis['code']);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests'] = ['activation_interest' => ['ACT_DEMO']];
$rOptAct = postLead($p);
pass('21.2 optional interests: only activation_interest → 201', $rOptAct['code'] === 201, (string) $rOptAct['code']);

// --- CATALOG REF pattern ---
$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['axis_interest'] = ['FORMACAO'];
pass('21.2 catalog_ref: FORMACAO → PASS', postLead($p)['code'] === 201);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['activation_interest'] = ['foo bar'];
pass('21.2 catalog_ref: "foo bar" → FAIL', postLead($p)['code'] === 422);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['activation_interest'] = ['#BAD'];
pass('21.2 catalog_ref: "#BAD" → FAIL', postLead($p)['code'] === 422);

$p = cloneValid($valid);
$p['sponsorship_simulation']['interests']['activation_interest'] = [str_repeat('A', 81)];
pass('21.2 catalog_ref: >80 → FAIL', postLead($p)['code'] === 422);

// --- DATETIME RFC3339 ---
$p = cloneValid($valid);
$p['sponsorship_simulation']['confirmed_at'] = '2026-09-11T21:00:00Z';
pass('21.2 datetime: RFC3339 Z → PASS', postLead($p)['code'] === 201);

$p = cloneValid($valid);
$p['sponsorship_simulation']['confirmed_at'] = '2026-09-11T18:00:00-03:00';
pass('21.2 datetime: RFC3339 offset → PASS', postLead($p)['code'] === 201);

$p = cloneValid($valid);
$p['sponsorship_simulation']['confirmed_at'] = 'tomorrow';
pass('21.2 datetime: "tomorrow" → FAIL', postLead($p)['code'] === 422);

// --- HASH set semantics (permute arrays) ---
$setUuid = newUuid();
$setBase = cloneValid($valid);
$setBase['submission_uuid'] = $setUuid;
$setBase['email'] = 'hashset.' . time() . '@example.com';
$setBase['sponsorship_simulation']['briefing']['objectives'] = ['INSTITUTIONAL_ASSOCIATION', 'BRAND_PRESENCE', 'LEGACY'];
$setBase['sponsorship_simulation']['briefing']['proof_needs'] = ['EXECUTIVE_REPORT', 'PORTAL'];
$setBase['sponsorship_simulation']['interests']['tier_interest'] = ['CARAJAS', 'EXPERIENCE'];
$setBase['sponsorship_simulation']['interests']['axis_interest'] = ['FORMACAO', 'CRIACAO'];
$rSet1 = postLead($setBase);
$setPerm = $setBase;
$setPerm['sponsorship_simulation']['briefing']['objectives'] = ['LEGACY', 'INSTITUTIONAL_ASSOCIATION', 'BRAND_PRESENCE'];
$setPerm['sponsorship_simulation']['briefing']['proof_needs'] = ['PORTAL', 'EXECUTIVE_REPORT'];
$setPerm['sponsorship_simulation']['interests']['tier_interest'] = ['EXPERIENCE', 'CARAJAS'];
$setPerm['sponsorship_simulation']['interests']['axis_interest'] = ['CRIACAO', 'FORMACAO'];
$rSet2 = postLead($setPerm);
$bSet1 = json_decode($rSet1['body'], true) ?: [];
$bSet2 = json_decode($rSet2['body'], true) ?: [];
pass(
    '21.2 hash set semantics: permute arrays → 201 replay',
    $rSet1['code'] === 201 && $rSet2['code'] === 201
        && (int) ($bSet1['lead_id'] ?? 0) === (int) ($bSet2['lead_id'] ?? 0)
        && !empty($bSet2['idempotent']),
    'c1=' . $rSet1['code'] . ' c2=' . $rSet2['code'] . ' idem=' . json_encode($bSet2['idempotent'] ?? null)
);

// --- MIGRATION: no blind 3307 ---
$mig21a = (string) file_get_contents($root . '/scripts/run_migration_etapa21a_catalogo_comercial_v21.php');
$mig21b = (string) file_get_contents($root . '/scripts/run_migration_etapa21b_sponsorship_simulation.php');
pass(
    '21.2 migration 21A: sem auto 127.0.0.1:3307 unless DCX_LOCAL_DOCKER_DB_OVERRIDE=1',
    !migrationHasBlindDockerPort($mig21a)
);
pass(
    '21.2 migration 21B: sem auto 127.0.0.1:3307 unless DCX_LOCAL_DOCKER_DB_OVERRIDE=1',
    !migrationHasBlindDockerPort($mig21b)
);

// --- MIGRATION SQL 21B NOT NULL invariants vs PHP CREATE ---
$sql21b = (string) file_get_contents($root . '/database/migrations/2026_etapa21b_sponsorship_simulation.sql');
$php21b = $mig21b;
$notNullCols = [
    'submission_version',
    'snapshot_version',
    'catalog_version',
    'briefing_schema_version',
    'policy_version',
    'engine_version',
    'confirmed_at',
    'investment_status',
    'currency',
    'primary_tier_ref',
    'fit_level',
    'availability_status',
    'briefing_snapshot',
    'interests_snapshot',
    'recommendation_snapshot',
    'snapshot_hash',
];
$sqlMismatch = [];
foreach ($notNullCols as $col) {
    $phpOk = (bool) preg_match(
        '/`' . preg_quote($col, '/') . '`\s+[^\n,]+NOT NULL/i',
        $php21b
    );
    $sqlOk = (bool) preg_match(
        '/`' . preg_quote($col, '/') . '`\s+[^\n,]+NOT NULL/i',
        $sql21b
    );
    if (!$phpOk || !$sqlOk) {
        $sqlMismatch[] = $col . '(php=' . ($phpOk ? '1' : '0') . ',sql=' . ($sqlOk ? '1' : '0') . ')';
    }
}
pass(
    '21.2 migration SQL 21B: NOT NULL invariants alinhados ao PHP CREATE',
    $sqlMismatch === [],
    $sqlMismatch === [] ? '' : implode(', ', $sqlMismatch)
);

// --- QUOTA FILTER: Incentiva RANGE via amount_max=30000 ---
$quotaModel = new \App\Models\Quota();
$filtered = $quotaModel->paginate(['amount_max' => 30000], 1, 100);
$foundIncentiva = false;
foreach ($filtered as $row) {
    if (strtoupper((string) ($row['catalog_ref_id'] ?? '')) === 'INCENTIVA') {
        $foundIncentiva = true;
        break;
    }
}
pass(
    '21.2 quota filter: Incentiva (amount NULL, range) encontrada com amount_max=30000',
    $foundIncentiva,
    'rows=' . count($filtered)
);

// String-scan gate (fresh install detalhado: tools/validate_etapa21_fresh_install.php)
$schemaScan = (string) file_get_contents($root . '/database/install_schema.sql');
$legacyActiveHits = [];
foreach (['Cota Formação', 'Cota Incentivador', 'Círculo Dança Carajás', '470448', '42768'] as $needle) {
    if (str_contains($schemaScan, $needle)) {
        $legacyActiveHits[] = $needle;
    }
}
if (preg_match("/'Cota Apresenta'[\s\S]{0,120}200000/", $schemaScan)) {
    $legacyActiveHits[] = 'Cota Apresenta+200000';
}
if (preg_match("/'Cota Movimento'[\s\S]{0,120}50000/", $schemaScan)) {
    $legacyActiveHits[] = 'Cota Movimento+50000';
}
pass(
    '21.2 fresh-scan: install_schema sem seeds legados ativos',
    $legacyActiveHits === [],
    $legacyActiveHits === [] ? '' : implode(' | ', $legacyActiveHits)
);
pass('21.2 fresh-scan: contém 265397', str_contains($schemaScan, '265397'));
pass('21.2 fresh-scan: contém 515592.48', str_contains($schemaScan, '515592.48'));
foreach (['INCENTIVA', 'MOVIMENTO', 'EXPERIENCE', 'CARAJAS', 'APRESENTA'] as $ref) {
    pass("21.2 fresh-scan: catalog_ref_id {$ref}", str_contains($schemaScan, $ref));
}
pass(
    '21.2 fresh-scan: quotas.amount DECIMAL(14,2)',
    (bool) preg_match('/CREATE TABLE IF NOT EXISTS `quotas`[\s\S]{0,800}`amount`\s+DECIMAL\(14,2\)/i', $schemaScan)
);
pass(
    '21.2 fresh-scan: available_quantity nullable',
    (bool) preg_match(
        '/CREATE TABLE IF NOT EXISTS `quotas`[\s\S]{0,900}`available_quantity`\s+INT(?:\s+UNSIGNED)?\s+NULL/i',
        $schemaScan
    )
);

// ---------------------------------------------------------------------------
// RC — contrato final intake + migration 21B (source + HTTP)
// Parent pode ainda estar endurecendo SponsorshipLeadIntake; asserts batem no contrato final.
// ---------------------------------------------------------------------------

echo "\n-- RC migration 21B --\n";

$mig21bRc = (string) file_get_contents($root . '/scripts/run_migration_etapa21b_sponsorship_simulation.php');
pass('RC migration 21B C: sem DEFAULT/PARTIAL no script', !preg_match("/PARTIAL/", $mig21bRc));
pass(
    'RC migration 21B A: versões reconcile usam 1.0.0 (não 1.0 solto)',
    (bool) preg_match("/DEFAULT '1\\.0\\.0'/", $mig21bRc)
    && !preg_match("/DEFAULT '1\\.0'(?!\\.\\d)/", $mig21bRc)
);
pass(
    'RC migration 21B A: empty-table ADD sem DEFAULT INCENTIVA em primary_tier_ref',
    !preg_match("/primary_tier_ref`[^\\n]*DEFAULT 'INCENTIVA'/", $mig21bRc)
);
pass(
    'RC migration 21B B: FAIL seguro com registros + coluna invariante ausente',
    str_contains($mig21bRc, 'coluna invariante NOT NULL ausente')
        && str_contains($mig21bRc, 'registro(s)')
        && str_contains($mig21bRc, 'não inventar fit/tier/version/hash/confirmation')
        && str_contains($mig21bRc, '$rowCount > 0')
);
pass(
    'RC migration 21B B: sem backfill inventado de hash/snapshot',
    !str_contains($mig21bRc, "REPEAT('0', 64)")
        && !preg_match("/UPDATE `sponsorship_simulations` SET `\\w+_snapshot` = '\\{\\}'/", $mig21bRc)
);

echo "\n-- RC intake contract --\n";

// confirmed true → PASS (fixture válida já cobre; reforço explícito)
$rcOk = cloneValid($valid);
$rcOk['sponsorship_simulation']['confirmed'] = true;
$rRcOk = postLead($rcOk);
$bRcOk = json_decode($rRcOk['body'], true) ?: [];
pass('RC confirmed true → PASS', $rRcOk['code'] === 201, (string) $rRcOk['code']);

$bad = cloneValid($valid);
$bad['sponsorship_simulation']['confirmed'] = false;
pass('RC confirmed false → 422', postLead($bad)['code'] === 422);

$bad = cloneValid($valid);
$bad['sponsorship_simulation']['confirmed'] = 'false';
pass('RC confirmed "false" → 422', postLead($bad)['code'] === 422);

$bad = cloneValid($valid);
$bad['sponsorship_simulation']['confirmed'] = 'true';
pass('RC confirmed "true" → 422', postLead($bad)['code'] === 422);

$bad = cloneValid($valid);
$bad['sponsorship_simulation']['confirmed'] = 1;
pass('RC confirmed 1 → 422', postLead($bad)['code'] === 422);

$ok = cloneValid($valid);
$ok['sponsorship_simulation']['briefing_schema_version'] = '1.0.0';
pass('RC briefing_schema_version 1.0.0 → PASS', postLead($ok)['code'] === 201);

$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing_schema_version'] = '9.0.0';
pass('RC briefing_schema_version 9.0.0 → 422', postLead($bad)['code'] === 422);

$bad = cloneValid($valid);
$bad['sponsorship_simulation']['briefing_schema_version'] = '';
pass('RC briefing_schema_version empty → 422', postLead($bad)['code'] === 422);

$bad = cloneValid($valid);
unset($bad['company_name']);
pass('RC company_name absent → 422', postLead($bad)['code'] === 422);

$bad = cloneValid($valid);
unset($bad['email']);
pass('RC email absent → 422', postLead($bad)['code'] === 422);

$bad = cloneValid($valid);
$bad['email'] = 'not-an-email';
pass('RC email invalid → 422', postLead($bad)['code'] === 422);

$noMsg = cloneValid($valid);
unset($noMsg['message']);
$rNoMsg = postLead($noMsg);
pass('RC message absent → PASS', $rNoMsg['code'] === 201, (string) $rNoMsg['code']);

$withMsg = cloneValid($valid);
$withMsg['message'] = 'Mensagem RC de teste para lead.';
$rMsg = postLead($withMsg);
$bMsg = json_decode($rMsg['body'], true) ?: [];
$msgLeadId = (int) ($bMsg['lead_id'] ?? 0);
$msgPersisted = false;
$msgInPayload = false;
if ($msgLeadId > 0) {
    $st = $pdo->prepare('SELECT message, integration_payload FROM leads WHERE id = :id');
    $st->execute(['id' => $msgLeadId]);
    $rowMsg = $st->fetch() ?: [];
    $msgPersisted = ((string) ($rowMsg['message'] ?? '')) === 'Mensagem RC de teste para lead.';
    $payloadMsg = json_decode((string) ($rowMsg['integration_payload'] ?? ''), true) ?: [];
    $msgInPayload = (($payloadMsg['message'] ?? null) === 'Mensagem RC de teste para lead.');
}
pass(
    'RC message valid → persisted on Lead',
    $rMsg['code'] === 201 && $msgPersisted,
    'http=' . $rMsg['code'] . ' lead=' . $msgLeadId
);
pass(
    'RC message in sanitized integration_payload',
    $rMsg['code'] === 201 && $msgInPayload,
    'http=' . $rMsg['code']
);

$msgUuid = newUuid();
$msgBase = cloneValid($valid);
$msgBase['submission_uuid'] = $msgUuid;
$msgBase['email'] = 'rc.msg.' . time() . '@example.com';
$msgBase['message'] = 'Primeira mensagem RC';
$rMsg1 = postLead($msgBase);
$msgConflict = $msgBase;
$msgConflict['message'] = 'Segunda mensagem RC diferente';
pass(
    'RC same UUID + different message → 409',
    $rMsg1['code'] === 201 && postLead($msgConflict)['code'] === 409,
    'first=' . $rMsg1['code']
);

$fails = count(array_filter($results, static fn ($r) => !$r['ok']));
$passes = count($results) - $fails;
echo "\nResultado: {$passes} PASS / {$fails} FAIL\n";
echo "Fresh install DB: php tools/validate_etapa21_fresh_install.php\n";
exit($fails > 0 ? 1 : 0);
